<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;

use App\Models\System;

class SystemsController extends BaseController
{

	public function index(Request $request)
	{
		if ($request->has('systems')) {
		} else {
			$rSystems = System::where('enabled', true)->get();
		}

		if ($request->has('basic') && $request->get('basic')) {
			$fields = [
				'_id',
				'name',
				'hasCharSheet',
			];
		} else {
			$fields = [
				'_id',
				'name',
				'sortName',
				'publisher',
				'genres',
				'lfg',
				'basics',
				'hasCharSheet',
				'enabled',
			];
		}

		$systems = [];
		foreach ($rSystems as $rSystem) {
			$system = [];
			foreach ($fields as $field) {
				$system[$field] = $rSystem->$field;
			}
			$systems[] = $system;
		}

		return [
			'success' => true,
			'systems' => $systems
		];
	}

}
