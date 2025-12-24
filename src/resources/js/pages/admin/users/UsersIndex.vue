<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { createColumnHelper, getCoreRowModel, getSortedRowModel, type SortingState, useVueTable } from '@tanstack/vue-table';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import users from '@/routes/admin/users';
import { useAxios } from '@/composables/useAxios';
import { type BreadcrumbItem, type Organization, type User } from '@/types';
import Button from '@/components/ui/button/Button.vue';

type UserRow = User & { organizations?: Organization[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: users.index().url },
];

const axios = useAxios();

const { data, isFetching, isError, refetch } = useQuery({
    queryKey: ['admin-users'],
    queryFn: async () => {
        const response = await axios.get(users.index().url);
        return (response.data?.data ?? response.data ?? []) as UserRow[];
    },
    staleTime: 60_000,
});

const rows = computed(() => data.value ?? []);

const columnHelper = createColumnHelper<UserRow>();
const sorting = ref<SortingState>([]);

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
        cell: ({ row }) => users.show(row.original.id).url,
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
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
});

const createUrl = '/admin/users/create';

const renderActionUrl = (value: unknown) => {
    if (typeof value === 'string') return value;
    return '';
};
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
        <Button
          as="a"
          :href="createUrl"
          variant="default"
        >
          Create user
        </Button>
      </div>

      <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <table class="min-w-full divide-y divide-border text-sm">
          <thead class="bg-muted/40">
            <tr>
              <template v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                <template v-for="header in headerGroup.headers" :key="header.id">
                  <th
                    class="px-4 py-3 text-left font-semibold text-foreground"
                    scope="col"
                  >
                    <span v-if="!header.isPlaceholder">
                      {{ header.column.columnDef.header as string }}
                    </span>
                  </th>
                </template>
              </template>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr
              v-if="isFetching"
              class="bg-background/40"
            >
              <td
                class="px-4 py-3 text-muted-foreground"
                :colspan="columns.length"
              >
                Loading users...
              </td>
            </tr>
            <tr
              v-else-if="isError"
              class="bg-background/40"
            >
              <td
                class="px-4 py-3 text-destructive"
                :colspan="columns.length"
              >
                Something went wrong loading users.
                <button
                  class="ml-2 text-primary underline underline-offset-2"
                  type="button"
                  @click="refetch()"
                >
                  Retry
                </button>
              </td>
            </tr>
            <template v-else-if="table.getRowModel().rows.length">
              <tr
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                class="hover:bg-muted/40"
              >
                <template v-for="cell in row.getVisibleCells()" :key="cell.id">
                  <td class="px-4 py-3 align-middle">
                    <template v-if="cell.column.id === 'actions'">
                      <Link :href="renderActionUrl(cell.getValue())">
                        <Button
                          as="span"
                          size="sm"
                          variant="secondary"
                        >
                          Edit
                        </Button>
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
              <td
                class="px-4 py-6 text-center text-muted-foreground"
                :colspan="columns.length"
              >
                No users found yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
