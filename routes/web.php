<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->group(['prefix' => 'auth'], function () use ($app) {
	$app->get('/validateCredentials', 'AuthController@validateCredentials');
	$app->get('/validateToken', [
		'middleware' => 'auth:orgAdmin',
		'uses' => 'AuthController@validateToken'
	]);
	$app->get('/orgMemberships/{userId:\d+}', 'AuthController@orgMemberships');
	$app->get('/validateMembership', 'AuthController@validateMembership');
});

$app->group(['prefix' => 'users'], function () use ($app) {
	$app->get('/exists', 'UsersController@exists');
	$app->post('/register', 'UsersController@register');
	$app->get('/activationLink', 'UserController@activationLink');
	$app->post('/activate', 'UserController@activateAccount');
	$app->post('/setPassword', 'UserController@setPassword');
	$app->post('/updatePassword', [
		'middleware' => 'auth',
		'uses' => 'UserController@updatePassword'
	]);

	$app->post('/toggleOrgAdmin', [
		'middleware' => 'auth:orgAdmin',
		'uses' => 'UserController@toggleOrgAdmin'
	]);
	$app->delete('/{userId:\d+}/org/{orgId:\d+}', [
		'middleware' => 'auth:orgAdmin',
		'uses' => 'UserController@removeFromOrg'
	]);
	$app->post('/', [
		'middleware' => 'auth:superAdmin',
		'uses' => 'UserController@store'
	]);
});

$app->group(['prefix' => 'games'], function () use ($app) {
	$app->get('/', 'GamesController@index');
	$app->get('/{org}', [
		'middleware' => 'auth',
		'uses' => 'OrgController@show'
	]);
	$app->patch('/{orgId:\d+}', [
		'middleware' => 'auth',
		'uses' => 'OrgController@update'
	]);

	$app->group(['prefix' => '{org}'], function () use ($app) {
		$app->get('users', [
			'middleware' => 'auth',
			'uses' => 'OrgUsersController@index'
		]);

		$app->group(['prefix' => 'types', 'middleware' => 'auth'], function () use ($app) {
			$app->get('/', 'SessionTypesController@index');
			$app->post('/', 'SessionTypesController@store');
			$app->patch('/{typeId:\d+}', 'SessionTypesController@update');
			$app->delete('/{typeId:\d+}', 'SessionTypesController@destroy');
		});
	});
});

$app->group(['prefix' => 'systems'], function () use ($app) {
	$app->get('/', 'SystemsController@index');
});
