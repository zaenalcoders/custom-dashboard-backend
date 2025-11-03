<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    abort(404);
});

Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', 'AuthController@login');
    Route::post('/forgot-password', 'AuthController@forgotPassword');
    Route::post('/reset-password', 'AuthController@resetPassword');
});


Route::group(['middleware' => 'auth'], function () {

    Route::group(['prefix' => 'file-uploader'], function () {
        Route::post('/create', 'FileUploaderController@create');
        Route::delete('/delete', 'FileUploaderController@delete');
    });

    Route::group(['prefix' => 'auth'], function () {
        Route::get('/logout', 'AuthController@logout');
        Route::get('/nav', 'AuthController@navigation');
        Route::get('/profile', 'AuthController@profile');
        Route::put('/refresh', 'AuthController@refresh');
        Route::put('/fcm-token', 'AuthController@fcm_token');
        Route::put('/update-profile', 'AuthController@updateProfile');
        Route::put('/change-password', 'AuthController@changePassword');
        Route::put('/change-profile-pic', 'AuthController@updateProfilePic');
    });

    Route::group(['prefix' => 'notifications'], function () {
        Route::get('/', 'NotificationController@index');
        Route::get('/count', 'NotificationController@count');
        Route::put('/read', 'NotificationController@read');
        Route::delete('/delete', 'NotificationController@delete');
    });

    Route::group(['prefix' => 'navigations'], function () {
        Route::get('/', 'NavigationController@index');
        Route::get('/{id:[a-zA-Z-?0-9]+}', 'NavigationController@index');
        Route::post('/create', 'NavigationController@create');
        Route::put('/update', 'NavigationController@update');
        Route::delete('/delete', 'NavigationController@delete');
    });

    Route::group(['prefix' => 'roles'], function () {
        Route::get('/', 'RoleController@index');
        Route::get('/download/{isDownload}', 'RoleController@index');
        Route::get('/{id:[a-zA-Z-?0-9]+}', 'RoleController@index');
        Route::post('/create', 'RoleController@create');
        Route::put('/update', 'RoleController@update');
        Route::put('/set-status', 'RoleController@setStatus');
        Route::delete('/delete', 'RoleController@delete');
    });

    Route::group(['prefix' => 'users'], function () {
        Route::get('/', 'UserController@index');
        Route::get('/download/{isDownload}', 'UserController@index');
        Route::get('/{id:[a-zA-Z-?0-9]+}', 'UserController@index');
        Route::post('/create', 'UserController@create');
        Route::put('/update', 'UserController@update');
        Route::put('/set-status', 'UserController@setStatus');
        Route::delete('/delete', 'UserController@delete');
    });

    Route::group(['prefix' => 'data-sources'], function () {
        Route::get('/', 'DataSourceController@index');
        Route::get('/{id:[a-zA-Z-?0-9]+}', 'DataSourceController@index');
        Route::post('/create', 'DataSourceController@create');
        Route::put('/update', 'DataSourceController@update');
        Route::delete('/delete', 'DataSourceController@delete');
    });

    Route::group(['prefix' => 'dashboards'], function () {
        Route::get('/list', 'DashboardController@getList');
        Route::get('/list/{id:[a-zA-Z-?0-9]+}', 'DashboardController@getList');
        Route::get('/chart/{id:[a-zA-Z-?0-9]+}', 'DashboardController@getChart');
        Route::post('/create', 'DashboardController@create');
        Route::put('/update', 'DashboardController@update');
        Route::delete('/delete', 'DashboardController@delete');
    });
});
