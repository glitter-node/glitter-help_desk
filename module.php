<?php

namespace Modules\Glitter\HelpDesk;

use App\Extension\AbstractModule;

class Module extends AbstractModule
{
    public function getPermissions(): array
    {
        $permissions = [
            'name' => ['ko' => '글리터 고객지원', 'en' => 'Glitter HelpDesk'],
            'description' => ['ko' => '고객 지원 티켓 권한', 'en' => 'Customer support ticket permissions'],
            'categories' => [
                [
                    'identifier' => 'tickets',
                    'name' => ['ko' => '티켓', 'en' => 'Tickets'],
                    'permissions' => [
                        ['action' => 'create', 'name' => ['ko' => '티켓 등록', 'en' => 'Create tickets'], 'type' => 'user', 'roles' => ['user']],
                        ['action' => 'read-categories', 'name' => ['ko' => '문의 분류 조회', 'en' => 'Read ticket categories'], 'type' => 'user', 'roles' => ['user']],
                        ['action' => 'read-own', 'name' => ['ko' => '내 티켓 조회', 'en' => 'Read own tickets'], 'type' => 'user', 'roles' => ['user']],
                        ['action' => 'reply', 'name' => ['ko' => '티켓 답변', 'en' => 'Reply to tickets'], 'type' => 'user', 'roles' => ['user']],
                        ['action' => 'read-assigned', 'name' => ['ko' => '담당 티켓 조회', 'en' => 'Read assigned tickets'], 'type' => 'admin', 'roles' => ['admin', 'manager', 'glitter-help_desk.agent']],
                        ['action' => 'read-all', 'name' => ['ko' => '전체 티켓 조회', 'en' => 'Read all tickets'], 'type' => 'admin', 'roles' => ['admin', 'manager']],
                        ['action' => 'assign', 'name' => ['ko' => '담당자 배정', 'en' => 'Assign tickets'], 'type' => 'admin', 'roles' => ['admin', 'manager']],
                        ['action' => 'update-status', 'name' => ['ko' => '상태 변경', 'en' => 'Update ticket status'], 'type' => 'admin', 'roles' => ['admin', 'manager', 'glitter-help_desk.agent']],
                        ['action' => 'update-priority', 'name' => ['ko' => '우선순위 변경', 'en' => 'Update ticket priority'], 'type' => 'admin', 'roles' => ['admin', 'manager', 'glitter-help_desk.agent']],
                        ['action' => 'respond', 'name' => ['ko' => '고객 답변', 'en' => 'Reply to tickets'], 'type' => 'admin', 'roles' => ['admin', 'manager', 'glitter-help_desk.agent']],
                    ],
                ],
                [
                    'identifier' => 'notes',
                    'name' => ['ko' => '내부 메모', 'en' => 'Internal notes'],
                    'type' => 'admin',
                    'permissions' => [
                        ['action' => 'manage', 'name' => ['ko' => '내부 메모 관리', 'en' => 'Manage internal notes'], 'type' => 'admin', 'roles' => ['admin', 'manager', 'glitter-help_desk.agent']],
                    ],
                ],
                [
                    'identifier' => 'categories',
                    'name' => ['ko' => '문의 분류', 'en' => 'Categories'],
                    'type' => 'admin',
                    'permissions' => [
                        ['action' => 'manage', 'name' => ['ko' => '분류 관리', 'en' => 'Manage categories'], 'type' => 'admin', 'roles' => ['admin']],
                    ],
                ],
                [
                    'identifier' => 'settings',
                    'name' => ['ko' => '설정', 'en' => 'Settings'],
                    'type' => 'admin',
                    'permissions' => [
                        ['action' => 'manage', 'name' => ['ko' => '설정 관리', 'en' => 'Manage settings'], 'type' => 'admin', 'roles' => ['admin']],
                    ],
                ],
                [
                    'identifier' => 'reports',
                    'name' => ['ko' => '통계', 'en' => 'Reports'],
                    'type' => 'admin',
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '통계 조회', 'en' => 'Read reports'], 'type' => 'admin', 'roles' => ['admin', 'manager']],
                    ],
                ],
            ],
        ];

        foreach ($permissions['categories'] as &$category) {
            $category['description'] ??= $category['name'];

            foreach ($category['permissions'] as &$permission) {
                $permission['description'] ??= $permission['name'];
            }
            unset($permission);
        }
        unset($category);

        return $permissions;
    }

    public function getRoles(): array
    {
        return [
            ['identifier' => 'glitter-help_desk.agent', 'name' => ['ko' => '헬프데스크 담당자', 'en' => 'HelpDesk Agent'], 'description' => ['ko' => '배정된 고객 지원 티켓을 처리합니다.', 'en' => 'Handles assigned customer support tickets.']],
        ];
    }

    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '고객지원', 'en' => 'HelpDesk'],
                'slug' => 'glitter-help_desk',
                'url' => null,
                'icon' => 'fas fa-headset',
                'order' => 35,
                'children' => [
                    ['name' => ['ko' => '대시보드', 'en' => 'Dashboard'], 'slug' => 'glitter-help_desk-dashboard', 'url' => '/admin/helpdesk', 'icon' => 'fas fa-chart-line', 'order' => 1, 'permission' => 'glitter-help_desk.reports.read'],
                    ['name' => ['ko' => '전체 티켓', 'en' => 'All Tickets'], 'slug' => 'glitter-help_desk-tickets', 'url' => '/admin/helpdesk/tickets', 'icon' => 'fas fa-ticket-alt', 'order' => 2, 'permission' => 'glitter-help_desk.tickets.read-all'],
                    ['name' => ['ko' => '내 담당 티켓', 'en' => 'Assigned Tickets'], 'slug' => 'glitter-help_desk-assigned', 'url' => '/admin/helpdesk/assigned', 'icon' => 'fas fa-user-check', 'order' => 3, 'permission' => 'glitter-help_desk.tickets.read-assigned'],
                    ['name' => ['ko' => '문의 분류', 'en' => 'Categories'], 'slug' => 'glitter-help_desk-categories', 'url' => '/admin/helpdesk/categories', 'icon' => 'fas fa-folder', 'order' => 4, 'permission' => 'glitter-help_desk.categories.manage'],
                    ['name' => ['ko' => '답변 템플릿', 'en' => 'Canned Replies'], 'slug' => 'glitter-help_desk-canned-replies', 'url' => '/admin/helpdesk/canned-replies', 'icon' => 'fas fa-comment-dots', 'order' => 5, 'permission' => 'glitter-help_desk.notes.manage'],
                    ['name' => ['ko' => '통계', 'en' => 'Reports'], 'slug' => 'glitter-help_desk-reports', 'url' => '/admin/helpdesk/reports', 'icon' => 'fas fa-chart-bar', 'order' => 6, 'permission' => 'glitter-help_desk.reports.read'],
                    ['name' => ['ko' => '설정', 'en' => 'Settings'], 'slug' => 'glitter-help_desk-settings', 'url' => '/admin/helpdesk/settings', 'icon' => 'fas fa-cog', 'order' => 7, 'permission' => 'glitter-help_desk.settings.manage'],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'author' => 'Glitter.kr',
            'brand' => 'Glitter.kr',
            'homepage' => 'https://glitter.kr',
        ];
    }
}
