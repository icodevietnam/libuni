<?php
/**
 * Roles - A Controller for managing the Users Authorization.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace App\Modules\Users\Controllers\Admin;

use Core\Config;
use Core\View;
use Helpers\Url;
use Helpers\ReCaptcha;

use App\Core\Controller;
use App\Models\Role;

use Carbon\Carbon;

use Auth;
use Hash;
use Input;
use Redirect;
use Session;
use Validator;


class Roles extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function before()
    {
        // Check the User Authorization.
        if (! Auth::user()->hasRole('administrator')) {
            $status = __d('users', 'You are not authorized to access this resource.');

            return Redirect::to('admin/dashboard')->withStatus($status, 'warning');
        }

        // Leave to parent's method the Execution Flow decisions.
        return parent::before();
    }

    protected function validate(array $data, $id = null)
    {
        if (! is_null($id)) {
            $ignore = ',' .intval($id);
        } else {
            $ignore =  '';
        }

        // The Validation rules.
        $rules = array(
            'name'        => 'required|min:4|max:40|valid_name',
            'slug'        => 'required|min:4|max:40|alpha_dash|unique:roles,name' .$ignore,
            'description' => 'required|min:5|max:255',
        );

        $attributes = array(
            'name'        => __d('users', 'Name'),
            'slug'        => __d('users', 'Slug'),
            'description' => __d('users', 'Description'),
        );

        // Add the custom Validation Rule commands.
        Validator::extend('valid_name', function($attribute, $value, $parameters)
        {
            $pattern = '~^(?:[\p{L}\p{Mn}\p{Pd}\'\x{2019}]+(?:$|\s+)){2,}$~u';

            return (preg_match($pattern, $value) === 1);
        });

        return Validator::make($data, $rules, array(), $attributes);
    }

    public function index()
    {
        // Get all Role records for current page.
        $roles = Role::with('users')->paginate(25);

        return $this->getView()
            ->shares('title', __d('users', 'Roles'))
            ->with('roles', $roles);
    }

    public function create()
    {
        return $this->getView()
            ->shares('title', __d('users', 'Create Role'));
    }

    public function store()
    {
        // Validate the Input data.
        $input = Input::only('name', 'slug', 'description');

        $validator = $this->validate($input);

        if($validator->passes()) {
            // Create a Role Model instance.
            Role::create($input);

            // Prepare the flash message.
            $status = __d('users', 'The Role <b>{0}</b> was successfully created.', $input['name']);

            return Redirect::to('admin/roles')->withStatus($status);
        }

        // Errors occurred on Validation.
        $status = $validator->errors();

        return Redirect::back()->withInput()->withStatus($status, 'danger');
    }

    public function show($id)
    {
        // Get the Role Model instance.
        $role = Role::find($id);

        if($role === null) {
            // There is no Role with this ID.
            $status = __d('users', 'Role not found: #{0}', $id);

            return Redirect::to('admin/roles')->withStatus($status, 'danger');
        }

        return $this->getView()
            ->shares('title', __d('users', 'Show Role'))
            ->with('role', $role);
    }

    public function edit($id)
    {
        // Get the Role Model instance.
        $role = Role::find($id);

        if($role === null) {
            // There is no Role with this ID.
            $status = __('Role not found: #{0}', $id);

            return Redirect::to('admin/roles')->withStatus($status, 'danger');
        }

        return $this->getView()
            ->shares('title', __d('users', 'Edit Role'))
            ->with('role', $role);
    }

    public function update($id)
    {
        // Get the Role Model instance.
        $role = Role::find($id);

        if($role === null) {
            // There is no Role with this ID.
            $status = __d('users', 'Role not found: #{0}', $id);

            return Redirect::to('admin/roles')->withStatus($status, 'danger');
        }

        // Validate the Input data.
        $input = Input::only('name', 'slug', 'description');

        $validator = $this->validate($input, $id);

        if($validator->passes()) {
            $origName = $role->name;

            // Update the Role Model instance.
            $role->name        = $input['name'];
            $role->slug        = $input['slug'];
            $role->description = $input['description'];

            // Save the Role information.
            $role->save();

            // Prepare the flash message.
            $status = __d('users', 'The Role <b>{0}</b> was successfully updated.', $origName);

            return Redirect::to('admin/roles')->withStatus($status);
        }

        // Errors occurred on Validation.
        $status = $validator->errors();

        return Redirect::back()->withInput()->withStatus($status, 'danger');
    }

    public function destroy($id)
    {
        // Get the Role Model instance.
        $role = Role::find($id);

        if($role === null) {
            // There is no Role with this ID.
            $status = __d('users', 'Role not found: #{0}', $id);

            return Redirect::to('admin/roles')->withStatus($status, 'danger');
        }

        // Destroy the requested Role record.
        $role->delete();

        // Prepare the flash message.
        $status = __d('users', 'The Role <b>{0}</b> was successfully deleted.', $role->name);

        return Redirect::to('admin/roles')->withStatus($status);
    }

}
