import { dashboard } from '@/routes';
import  opportunities from '@/routes/opportunities';
import { BookOpen, Folder, HandHeart, LayoutGrid } from 'lucide-vue-next';
import { type NavItem } from '@/types';

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
      {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
