<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserResourceQueryTest extends TestCase
{
    public function test_serializing_an_unloaded_role_does_not_query_the_database(): void
    {
        $user = new User(['user_id' => 'U-1', 'role_id' => 2, 'first_name' => 'HQ']);
        $reads = 0;
        DB::listen(function () use (&$reads): void { $reads++; });

        $data = (new UserResource($user))->toArray(Request::create('/api/v1/auth/me'));

        $this->assertSame('admin', $data['role']['role_key']);
        $this->assertSame('Admin', $data['role']['role_name']);
        $this->assertSame(0, $reads);
    }
}
