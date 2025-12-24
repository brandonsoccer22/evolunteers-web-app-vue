import { dashboard } from '@/routes/admin';
import opportunities from '@/routes/admin/opportunities';
import users from '@/routes/admin/users';
import { type NavItem } from '@/types';
import { HandHeart, LayoutGrid, Users } from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Opportunities',
        href: opportunities.index().url,
        icon: HandHeart,
    },
    {
        title: 'Users',
        href: users.index().url,
        icon: Users,
    },
    // ...other items
];

export const footerNavItems: NavItem[] = [
    //   {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
