<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import {
    createColumnHelper,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
    type ColumnFiltersState,
    type SortingState,
} from '@tanstack/vue-table';
import { computed, ref } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import { useAxios } from '@/composables/useAxios';
import AppLayout from '@/layouts/AppLayout.vue';
import userRoutes from '@/routes/admin/users';
import { type BreadcrumbItem, type Organization, type User } from '@/types';

type UserRow = User & { organizations?: Organization[] };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: userRoutes.index().url }];

const axios = useAxios();

const { data, isFetching, isError, refetch } = useQuery({
    queryKey: ['admin-users'],
    queryFn: async () => {
        const response = await axios.get(userRoutes.index().url);
        return (response.data?.data ?? response.data ?? []) as UserRow[];
    },
    staleTime: 60_000,
});

const rows = computed(() => data.value ?? []);

const columnHelper = createColumnHelper<UserRow>();
const sorting = ref<SortingState>([]);
const globalFilter = ref('');
const columnFilters = ref<ColumnFiltersState>([]);

const columns = [
    columnHelper.accessor((row) => `${row.first_name} ${row.last_name}`, {
        id: 'name',
        header: 'Name',
        cell: (info) => info.getValue(),
    }),
    columnHelper.accessor('email', {
        header: 'Email',
        cell: (info) => info.getValue(),
    }),
    columnHelper.display({
        id: 'organizations',
        header: 'Organizations',
        cell: ({ row }) => row.original.organizations?.length ?? 0,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => row.original.id,
    }),
];

const table = useVueTable({
    get data() {
        return rows.value;
    },
    columns,
    state: {
        get sorting() {
            return sorting.value;
        },
        get globalFilter() {
            return globalFilter.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
    },
    onGlobalFilterChange: (updater) => {
        globalFilter.value = typeof updater === 'function' ? updater(globalFilter.value) : updater;
    },
    onColumnFiltersChange: (updater) => {
        columnFilters.value = typeof updater === 'function' ? updater(columnFilters.value) : updater;
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
});

const createUrl = '/admin/users/create';
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Users</h1>
                    <p class="text-sm text-muted-foreground">Manage user accounts and their organizations.</p>
                </div>
                <Button as="a" :href="createUrl" variant="default"> Create user </Button>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-muted/30 p-3 dark:border-sidebar-border">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Search</label>
                        <input
                            v-model="globalFilter"
                            type="search"
                            class="h-9 w-full rounded-md border border-input px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/60"
                            placeholder="Search all columns"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Name</label>
                        <input
                            :value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
                            type="search"
                            class="h-9 w-full rounded-md border border-input px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/60"
                            placeholder="Filter name"
                            @input="table.getColumn('name')?.setFilterValue(($event.target as HTMLInputElement).value)"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Email</label>
                        <input
                            :value="(table.getColumn('email')?.getFilterValue() as string) ?? ''"
                            type="search"
                            class="h-9 w-full rounded-md border border-input px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/60"
                            placeholder="Filter email"
                            @input="table.getColumn('email')?.setFilterValue(($event.target as HTMLInputElement).value)"
                        />
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <template v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                                <template v-for="header in headerGroup.headers" :key="header.id">
                                    <th class="px-4 py-3 text-left font-semibold text-foreground" scope="col">
                                        <span v-if="!header.isPlaceholder">
                                            <button
                                                v-if="header.column.getCanSort?.()"
                                                type="button"
                                                class="flex items-center gap-1"
                                                @click="header.column.toggleSorting()"
                                            >
                                                <span>{{ header.column.columnDef.header as string }}</span>
                                                <span class="text-xs text-muted-foreground">
                                                    {{
                                                        header.column.getIsSorted() === 'asc'
                                                            ? '↑'
                                                            : header.column.getIsSorted() === 'desc'
                                                              ? '↓'
                                                              : ''
                                                    }}
                                                </span>
                                            </button>
                                            <span v-else>
                                                {{ header.column.columnDef.header as string }}
                                            </span>
                                        </span>
                                    </th>
                                </template>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="isFetching" class="bg-background/40">
                            <td class="px-4 py-3 text-muted-foreground" :colspan="columns.length">Loading users...</td>
                        </tr>
                        <tr v-else-if="isError" class="bg-background/40">
                            <td class="px-4 py-3 text-destructive" :colspan="columns.length">
                                Something went wrong loading users.
                                <button class="ml-2 text-primary underline underline-offset-2" type="button" @click="refetch()">Retry</button>
                            </td>
                        </tr>
                        <template v-else-if="table.getRowModel().rows.length">
                            <tr v-for="row in table.getRowModel().rows" :key="row.id" class="hover:bg-muted/40">
                                <template v-for="cell in row.getVisibleCells()" :key="cell.id">
                                    <td class="px-4 py-3 align-middle">
                                        <template v-if="cell.column.id === 'actions'">
                                            <Link :href="userRoutes.show(row.original.id).url">
                                                <Button as="span" size="sm" variant="secondary"> Edit </Button>
                                            </Link>
                                        </template>
                                        <template v-else>
                                            {{ cell.renderValue() as string }}
                                        </template>
                                    </td>
                                </template>
                            </tr>
                        </template>
                        <tr v-else>
                            <td class="px-4 py-6 text-center text-muted-foreground" :colspan="columns.length">No users found yet.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div class="text-muted-foreground">
                        Showing
                        <span class="font-semibold text-foreground">{{ table.getRowModel().rows.length }}</span>
                        of
                        <span class="font-semibold text-foreground">{{ rows.length }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            class="rounded-md border px-3 py-1 text-sm disabled:opacity-50"
                            :disabled="!table.getCanPreviousPage()"
                            @click="table.previousPage()"
                        >
                            Previous
                        </button>
                        <button
                            class="rounded-md border px-3 py-1 text-sm disabled:opacity-50"
                            :disabled="!table.getCanNextPage()"
                            @click="table.nextPage()"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
