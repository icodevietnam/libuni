<?php
/**
 * Users - A Controller for managing the Users Authentication.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace App\Modules\Users\Controllers\Admin;

use Core\View;
use Helpers\Url;
use Helpers\ReCaptcha;

use App\Core\Controller;
use App\Models\Role;
use App\Models\User;

use Carbon\Carbon;

use Auth;
use Hash;
use Input;
use Redirect;
use Session;
use Validator;


class Users extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function before()
    {
        // Check the User Authorization.
        switch ($this->getMethod()) {
            case 'profile':
            case 'postProfile':
                break;

            default:
                if (! Auth::user()->hasRole('administrator')) {
                    $status = __d('users', 'You are not authorized to access this resource.');

                    return Redirect::to('admin/dashboard')->withStatus($status, 'warning');
                }
        }

        // Leave to parent's method the Execution Flow decisions.
        return parent::before();
    }

    protected function validate(array $data, $id = null)
    {
        if (! is_null($id)) {
            $ignore = ',' .intval($id);

            $required = 'sometimes|required';
        } else {
            $ignore = '';

            $required = 'required';
        }

        // The Validation rules.
        $rules = array(
            'username'              => 'required|min:4|max:100|alpha_dash|unique:users,username' .$ignore,
            'role'                  => 'required|numeric|exists:roles,id',
            'realname'              => 'required|min:5|max:100|valid_name',
            'password'              => $required .'|confirmed|strong_password',
            'password_confirmation' => $required .'|same:password',
            'email'                 => 'required|min:5|max:100|email',
        );

        $messages = array(
            'valid_name'      => __d('users', 'The :attribute field is not a valid name.'),
            'strong_password' => __d('users', 'The :attribute field is not strong enough.'),
        );

        $attributes = array(
            'username'              => __d('users', 'Username'),
            'role'                  => __d('users', 'Role'),
            'realname'              => __d('users', 'Name and Surname'),
            'password'              => __d('users', 'Password'),
            'password_confirmation' => __d('users', 'Password confirmation'),
            'email'                 => __d('users', 'E-mail'),
        );

        // Add the custom Validation Rule commands.
        Validator::extend('valid_name', function($attribute, $value, $parameters)
        {
            $pattern = '~^(?:[\p{L}\p{Mn}\p{Pd}\'\x{2019}]+(?:$|\s+)){2,}$~u';

            return (preg_match($pattern, $value) === 1);
        });

        Validator::extend('strong_password', function($attribute, $value, $parameters)
        {
            $pattern = "/(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/";

            return (preg_match($pattern, $value) === 1);
        });

        return Validator::make($data, $rules, $messages, $attributes);
    }

    public function index()
    {
        // Get all User records for current page.
        $users = User::where('active', 1)->paginate(25);

        return $this->getView()
            ->shares('title', __d('users', 'Users'))
            ->with('users', $users);
    }

    public function create()
    {
        // Get all available User Roles.
        $roles = Role::all();

        return $this->getView()
            ->shares('title', __d('users', 'Create User'))
            ->with('roles', $roles);
    }

    public function store()
    {
        // Validate the Input data.
        $input = Input::only('username', 'role', 'realname', 'password', 'password_confirmation', 'email');

        $validator = $this->validate($input);

        if($validator->passes()) {
            // Encrypt the given Password.
            $password = Hash::make($input['password']);

            // Create a User Model instance.
            User::create(array(
                'username' => $input['username'],
                'password' => $password,
                'role_id'  => $input['role'],
                'realname' => $input['realname'],
                'email'    => $input['email'],
                'active'   => 1,
            ));

            // Prepare the flash message.
            $status = __d('users', 'The User <b>{0}</b> was successfully created.', $input['username']);

            return Redirect::to('admin/users')->withStatus($status);
        }

        // Errors occurred on Validation.
        $status = $validator->errors();

        return Redirect::back()->withInput()->withStatus($status, 'danger');
    }

    public function show($id)
    {
        // Get the User Model instance.
        $user = User::find($id);

        if($user === null) {
            // There is no User with this ID.
            $status = __d('users', 'User not found: #{0}', $id);

            return Redirect::to('admin/users')->withStatus($status, 'danger');
        }

        return $this->getView()
            ->shares('title', __d('users', 'Show User'))
            ->with('user', $user);
    }

    public function edit($id)
    {
        // Get the User Model instance.
        $user = User::find($id);

        if($user === null) {
            // There is no User with this ID.
            $status = __d('users', 'User not found: #{0}', $id);

            return Redirect::to('admin/users')->withStatus($status, 'danger');
        }

        // Get all available User Roles.
        $roles = Role::all();

        return $this->getView()
            ->shares('title', __d('users', 'Edit User'))
            ->with('roles', $roles)
            ->with('user', $user);
    }

    public function update($id)
    {
        // Get the User Model instance.
        $user = User::find($id);

        if($user === null) {
            // There is no User with this ID.
            $status = __d('users', 'User not found: #{0}', $id);

            return Redirect::to('admin/users')->withStatus($status, 'danger');
        }

        // Validate the Input data.
        $input = Input::only('username', 'role', 'realname', 'password', 'password_confirmation', 'email');

        if(empty($input['password']) && empty($input['password_confirm'])) {
            unset($input['password']);
            unset($input['password_confirmation']);
        }

        $validator = $this->validate($input, $id);

        if($validator->passes()) {
            $origName = $user->username;

            // Update the User Model instance.
            $user->username = $input['username'];
            $user->role_id  = $input['role'];
            $user->realname = $input['realname'];
            $user->email    = $input['email'];

            if(isset($input['password'])) {
                // Encrypt and add the given Password.
                $user->password = Hash::make($input['password']);
            }

            // Save the User information.
            $user->save();

            // Prepare the flash message.
            $status = __d('users', 'The User <b>{0}</b> was successfully updated.', $origName);

            return Redirect::to('admin/users')->withStatus($status);
        }

        // Errors occurred on Validation.
        $status = $validator->errors();

        return Redirect::back()->withInput()->withStatus($status, 'danger');
    }

    public function destroy($id)
    {
        // Get the User Model instance.
        $user = User::find($id);

        if($user === null) {
            // There is no User with this ID.
            $status = __d('users', 'User not found: #{0}', $id);

            return Redirect::to('admin/users')->withStatus($status, 'danger');
        }

        // Destroy the requested User record.
        $user->delete();

        // Prepare the flash message.
        $status = __d('users', 'The User <b>{0}</b> was successfully deleted.', $user->username);

        return Redirect::to('admin/users')->withStatus($status);
    }

    public function profile()
    {
        $user = Auth::user();

        return $this->getView()
            ->shares('title',  __d('users', 'User Profile'))
            ->with('user', $user);
    }

    public function postProfile()
    {
        $user = Auth::user();

        // Retrieve the Input data.
        $input = Input::only('current_password', 'password', 'password_confirmation');

        // Prepare the Validation Rules, Messages and Attributes.
        $rules = array(
            'current_password'      => 'required|valid_password',
            'password'              => 'required|strong_password',
            'password_confirmation' => 'required|same:password',
        );

        $messages = array(
            'valid_password'  => __d('users', 'The :attribute field is invalid.'),
            'strong_password' => __d('users', 'The :attribute field is not strong enough.'),
        );

        $attributes = array(
            'current_password'      => __d('users', 'Current Password'),
            'password'              => __d('users', 'New Password'),
            'password_confirmation' => __d('users', 'Password Confirmation'),
        );

        // Add the custom Validation Rule commands.
        Validator::extend('valid_password', function($attribute, $value, $parameters) use ($user)
        {
            return Hash::check($value, $user->password);
        });

        Validator::extend('strong_password', function($attribute, $value, $parameters)
        {
            $pattern = "/(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/";

            return (preg_match($pattern, $value) === 1);
        });

        // Create a Validator instance.
        $validator = Validator::make($input, $rules, $messages, $attributes);

        // Validate the Input.
        if ($validator->passes()) {
            $password = $input['password'];

            // Update the password on the User Model instance.
            $user->password = Hash::make($password);

            // Save the User Model instance.
            $user->save();

            // Use a Redirect to avoid the reposting the data.
            $status = __d('users', 'You have successfully updated your Password.');

            return Redirect::back()->withStatus($status);
        }

        // Collect the Validation errors.
        $status = $validator->errors()->all();

        return Redirect::back()->withStatus($status, 'danger');
    }

    public function search()
    {
        // Validation rules
        $rules = array(
            'query' => 'required|min:4|valid_query'
        );

        $messages = array(
            'valid_query' => __d('users', 'The :attribute field is not a valid query string.'),
        );

        $attributes = array(
            'query' => __('Search Query'),
        );

        // Add the custom Validation Rule commands.
        Validator::extend('valid_query', function($attribute, $value, $parameters)
        {
            return (preg_match('/^[\p{L}\p{N}_\-\s]+$/', $value) === 1);
        });

        // Validate the Input data.
        $input = Input::only('query');

        $validator = Validator::make($input, $rules, $messages, $attributes);

        if($validator->fails()) {
            // Prepare the flash message.
            $status = $validator->errors();

            return Redirect::back()->withStatus($status, 'danger');
        }

        // Search the Records on Database.
        $search = $input['query'];

        $users = User::where('username', 'LIKE', '%' .$search .'%')
            ->orWhere('realname', 'LIKE', '%' .$search .'%')
            ->orWhere('email', 'LIKE', '%' .$search .'%')
            ->get();

        // Prepare the Query for displaying.
        $search = htmlentities($search);

        return $this->getView()
            ->shares('title', __d('users', 'Searching Users for: {0}', $search))
            ->with('search', $search)
            ->with('users', $users);
    }
}
