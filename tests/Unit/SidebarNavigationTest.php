<?php

test('admin sidebar links are organized into the requested groups', function () {
    $projectRoot = dirname(__DIR__, 2);
    $sidebar = file_get_contents($projectRoot.'/resources/js/components/app-sidebar.tsx');
    $navigation = file_get_contents($projectRoot.'/resources/js/components/nav-main.tsx');

    expect($sidebar)
        ->not->toBeFalse()
        ->and($navigation)->not->toBeFalse();

    $sidebar = preg_replace('/\s+/', ' ', $sidebar);

    expect($sidebar)->toContain(
        "title: 'Main', items: [ { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid, }, { title: 'Schedules', url: '/admin/schedules', icon: CalendarClock, }, ], }, { title: 'User Management', items: [ { title: 'Users', url: '/admin/users', icon: Users, }, ], }, { title: 'Utilities', items: [ { title: 'Vehicles', url: '/admin/vehicles', icon: BusFront, }, { title: 'Routes', url: '/admin/routes', icon: Route, }, { title: 'Drivers', url: '/admin/drivers', icon: UserRound, }, ], }",
    );

    expect($navigation)
        ->toContain('groups.map((group) => (')
        ->toContain('<SidebarGroupLabel>{group.title}</SidebarGroupLabel>')
        ->toContain('group.items.map((item) => (');

    expect($sidebar)->toContain("auth.user.user_type === 'ADMIN' || !item.url.startsWith('/admin/')");
});
