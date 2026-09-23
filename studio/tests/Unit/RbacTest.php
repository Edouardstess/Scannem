<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Role;
use Tests\Support\Factory;
use Tests\Support\TestCase;

final class RbacTest extends TestCase
{
    public function testSuperAdminHoldsEveryPermission(): void
    {
        foreach (Role::PERMISSIONS as $permission) {
            $this->assertTrue(
                Role::grants(Role::SUPER_ADMIN, $permission),
                'SUPER_ADMIN should hold ' . $permission
            );
        }
    }

    public function testPhotographerCannotManageUsers(): void
    {
        $this->assertTrue(Role::grants(Role::PHOTOGRAPHER, 'gallery.create'));
        $this->assertTrue(Role::grants(Role::PHOTOGRAPHER, 'photo.delete'));
        $this->assertFalse(Role::grants(Role::PHOTOGRAPHER, 'user.manage'));
    }

    public function testEditorCannotDeleteGalleriesOrPhotos(): void
    {
        $this->assertTrue(Role::grants(Role::EDITOR, 'gallery.update'));
        $this->assertTrue(Role::grants(Role::EDITOR, 'photo.upload'));
        $this->assertFalse(Role::grants(Role::EDITOR, 'gallery.delete'));
        $this->assertFalse(Role::grants(Role::EDITOR, 'photo.delete'));
        $this->assertFalse(Role::grants(Role::EDITOR, 'settings.manage'));
    }

    public function testUnknownRoleGrantsNothing(): void
    {
        $this->assertFalse(Role::grants('ADMIN_OF_EVERYTHING', 'gallery.view'));
        $this->assertFalse(Role::isValid('ADMIN_OF_EVERYTHING'));
    }

    public function testAuthReflectsTheLoggedInUsersRole(): void
    {
        $editor = Factory::user('editor@example.test', 'correct-horse-battery', Role::EDITOR);
        Auth::login($editor, new Request('GET', '/admin', [], [], ['HTTP_USER_AGENT' => 'PHPUnit'], [], []));

        $this->assertSame(Role::EDITOR, Auth::role());
        $this->assertTrue(Auth::can('gallery.update'));
        $this->assertTrue(Auth::cannot('gallery.delete'));

        Auth::logout();

        $this->assertFalse(Auth::can('gallery.update'), 'A logged-out visitor holds no permission at all.');
    }
}
