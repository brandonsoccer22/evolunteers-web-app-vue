import { dashboard } from '@/routes/admin';
import opportunities from '@/routes/admin/opportunities';
import { type NavItem } from '@/types';
import { HandHeart, LayoutGrid } from 'lucide-vue-next';

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
