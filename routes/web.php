<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});
Route::get("/send",function(){
   $test =  Mail::raw('This is a test email', function ($message) {
        $message->to('4amir.amro@gmail.com')
            ->subject('Test Email');
    });
   dd($test);
});
