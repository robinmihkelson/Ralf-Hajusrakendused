<?php

use App\Models\BlogPost;
use App\Models\User;

test('non admin users cannot delete another users blog post', function () {
    $owner = User::factory()->create();
    $newUser = User::factory()->create();
    $post = BlogPost::create([
        'user_id' => $owner->id,
        'title' => 'Protected post',
        'description' => 'This should not be deleted by another account.',
    ]);

    $response = $this
        ->actingAs($newUser)
        ->deleteJson("/blog/posts/{$post->id}");

    $response
        ->assertForbidden()
        ->assertJson([
            'error' => 'Only the author or administrator can manage posts.',
        ]);

    $this->assertDatabaseHas('blog_posts', ['id' => $post->id]);
});

test('post authors can delete their own blog posts', function () {
    $owner = User::factory()->create();
    $post = BlogPost::create([
        'user_id' => $owner->id,
        'title' => 'My post',
        'description' => 'I should be able to delete this.',
    ]);

    $this->actingAs($owner)
        ->deleteJson("/blog/posts/{$post->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
});

test('admins can delete blog posts they do not own', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $post = BlogPost::create([
        'user_id' => $owner->id,
        'title' => 'Admin managed post',
        'description' => 'Admins should still be able to moderate posts.',
    ]);

    $this->actingAs($admin)
        ->deleteJson("/blog/posts/{$post->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
});
