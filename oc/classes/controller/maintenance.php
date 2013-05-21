<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Maintenance extends Kohana_Controller {

	public function action_index()
	{
        //in case there's no maintenance go back to the home.
        if (core::config('general.maintenance')!=1)
            Request::current()->redirect(Route::url('default'));

        $this->response->body(__('We are working on our site, please visit later. Thanks'));
	}


} // End 
