<?php

namespace Http;

use Http\ResponseTrait;
use Session\Store as SessionStore;
use Support\Contracts\MessageProviderInterface;
use Support\MessageBag;

use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;


class RedirectResponse extends SymfonyRedirectResponse
{
    use ResponseTrait;

    /**
     * The request instance.
     *
     * @var \Http\Request
     */
    protected $request;

    /**
     * The session store implementation.
     *
     * @var \Session\Store
     */
    protected $session;


    /**
     * Flash a piece of data to the session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return \Http\RedirectResponse
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) $this->with($k, $v);
        } else {
            $this->session->flash($key, $value);
        }

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  array  $input
     * @return \Http\RedirectResponse
     */
    public function withInput(array $input = null)
    {
        $input = $input ?: $this->request->input();

        $this->session->flashInput($input);

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  dynamic  string
     * @return \Http\RedirectResponse
     */
    public function onlyInput()
    {
        return $this->withInput($this->request->only(func_get_args()));
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  dynamic  string
     * @return \Http\RedirectResponse
     */
    public function exceptInput()
    {
        return $this->withInput($this->request->except(func_get_args()));
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param  \Support\Contracts\MessageProviderInterface|array  $provider
     * @return \Http\RedirectResponse
     */
    public function withErrors($provider)
    {
        if ($provider instanceof MessageProviderInterface) {
            $this->with('errors', $provider->getMessageBag());
        } else {
            $this->with('errors', new MessageBag((array) $provider));
        }

        return $this;
    }

    /**
     * Flash a array containing a message to the session.
     *
     * @param string $message
     * @param string $type
     *
     * @return \Http\RedirectResponse
     */
    public function withStatus($message, $type = 'success')
    {
        $status = array('type' => $type, 'text' => $message);

        $this->session->push('status', $status);

        return $this;
    }

    /**
     * Get the Request instance.
     *
     * @return  \Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the Request instance.
     *
     * @param  \Http\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the Session Store implementation.
     *
     * @return \Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set the Session Store implementation.
     *
     * @param \Session\Store  $store
     * @return void
     */
    public function setSession(SessionStore $session)
    {
        $this->session = $session;
    }

    /**
     * Dynamically bind flash data in the Session.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (str_starts_with($method, 'with')) {
            return $this->with(lcfirst(substr($method, 4)), $parameters[0]);
        }

        throw new \BadMethodCallException("Method [$method] does not exist on Redirect.");
    }
}
