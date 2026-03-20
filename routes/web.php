<?php

use App\Http\Controllers\MarkerController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/weather', [WeatherController::class, 'index'])->name('weather.current');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('blog', function () {
        return Inertia::render('Blog');
    })->name('blog');

    Route::get('eshop', function () {
        return Inertia::render('EShop');
    })->name('eshop');

    Route::get('/blog/posts', [BlogPostController::class, 'index'])->name('blog.posts.index');
    Route::post('/blog/posts', [BlogPostController::class, 'store'])->name('blog.posts.store');
    Route::get('/blog/posts/{post}', [BlogPostController::class, 'show'])->name('blog.posts.show');
    Route::put('/blog/posts/{post}', [BlogPostController::class, 'update'])->name('blog.posts.update');
    Route::delete('/blog/posts/{post}', [BlogPostController::class, 'destroy'])->name('blog.posts.destroy');

    Route::get('/blog/posts/{post}/comments', [BlogCommentController::class, 'index'])->name('blog.post.comments.index');
    Route::post('/blog/posts/{post}/comments', [BlogCommentController::class, 'store'])->name('blog.post.comments.store');
    Route::delete('/blog/comments/{comment}', [BlogCommentController::class, 'destroy'])->name('blog.comments.destroy');

    Route::get('/dashboard/markers', [MarkerController::class, 'index'])->name('dashboard.markers.index');
    Route::post('/dashboard/markers', [MarkerController::class, 'store'])->name('dashboard.markers.store');
    Route::get('/dashboard/markers/{marker}', [MarkerController::class, 'show'])->name('dashboard.markers.show');
    Route::put('/dashboard/markers/{marker}', [MarkerController::class, 'update'])->name('dashboard.markers.update');
    Route::delete('/dashboard/markers/{marker}', [MarkerController::class, 'destroy'])->name('dashboard.markers.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
