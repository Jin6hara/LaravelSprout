<?php

namespace App\Services\Post;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostSendService
{
    public function send(User $author, array $data, array $recipientIds, array $files = []): Post
    {
        return DB::transaction(function () use ($author, $data, $recipientIds, $files) {
            $post = Post::create([
                'user_id'       => $author->id,
                'type'          => $data['type'],
                'title'         => $data['title'] ?? null,
                'body'          => $data['body'],
                'expires_at'    => $data['expires_at'] ?? null,
                'allow_replies' => $data['allow_replies'],
            ]);

            $recipientIds = array_values(array_diff($recipientIds, [$author->id]));
            if ($recipientIds) {
                $post->viewers()->attach($recipientIds, ['confirmed_at' => null]);
            }

            foreach ($files as $file) {
                $folder = 'attachments/' . now()->format('Y/m');
                $path = $file->store($folder, 's3');
                if ($path === false) {
                    throw new \RuntimeException('ファイルのアップロードに失敗しました。');
                }

                $post->attachments()->create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size'          => $file->getSize(),
                ]);
            }

            return $post;
        });
    }
}
