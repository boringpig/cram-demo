<?php


namespace App\Http\Controllers;


class AdminController extends Controller
{

	/**
	 * AdminController constructor.
	 */
	public function __construct()
	{
		$this->middleware(['auth','permission:後端管理']);
	}

}