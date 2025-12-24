<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { createColumnHelper, getCoreRowModel, getSortedRowModel, type SortingState, useVueTable } from '@tanstack/vue-table';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAxios } from '@/composables/useAxios';
import opportunitiesRoutes from '@/routes/admin/opportunities';
import type { BreadcrumbItem, Opportunity, PaginatedResponse, PaginationMeta } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Opportunities', href: opportunitiesRoutes.index().url },
];

const axios = useAxios();

const globalSearch = ref('');
const nameFilter = ref('');
const descriptionFilter = ref('');
const page = ref(1);
const perPage = ref(10);
const sorting = ref<SortingState>([]);

watch([globalSearch, nameFilter, descriptionFilter], () => {
    page.value = 1;
});
watch(
    () => sorting.value,
    () => {
        page.value = 1;
    },
    { deep: true },
);

const { data, isFetching, isError, refetch } = useQuery({
    queryKey: computed(() => ['admin-opportunities', page.value, perPage.value, globalSearch.value, nameFilter.value, descriptionFilter.value, sorting.value]),
    queryFn: async () => {
        const sort = sorting.value[0];
        const params: Record<string, unknown> = {
            page: page.value,
            per_page: perPage.value,
            search: globalSearch.value || undefined,
            name: nameFilter.value || undefined,
            description: descriptionFilter.value || undefined,
        };
        if (sort?.id) {
            params.sort = sort.id;
            params.direction = sort.desc ? 'desc' : 'asc';
        }
        const response = await axios.get(opportunitiesRoutes.index().url, {
            params,
        });
        const body = response.data as PaginatedResponse<Opportunity> | { data: Opportunity[]; meta?: PaginationMeta };
        return {
            data: (body as PaginatedResponse<Opportunity>).data ?? (response.data?.data ?? response.data ?? []),
            meta: (body as PaginatedResponse<Opportunity>).meta,
        } satisfies PaginatedResponse<Opportunity>;
    },
    //keepPreviousData: true,
    staleTime: 30_000,
});

const rows = computed(() => data.value?.data ?? []);
const meta = computed<PaginationMeta | undefined>(() => data.value?.meta ?? undefined);

const columnHelper = createColumnHelper<Opportunity>();

const columns = [
    columnHelper.accessor('name', {
        header: 'Name',
        cell: (info) => info.getValue(),
    }),
    columnHelper.accessor('start_date', {
        header: 'Start date',
        cell: (info) => info.getValue() || '—',
    }),
    columnHelper.accessor((row) => row.description ?? '', {
        id: 'description',
        header: 'Description',
        cell: (info) => info.getValue(),
    }),
    columnHelper.display({
        id: 'organizations',
        header: 'Organizations',
        cell: ({ row }) => row.original.organizations?.length ?? 0,
        enableSorting: false,
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => opportunitiesRoutes.show(row.original.id).url,
        enableSorting: false,
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

const createUrl = opportunitiesRoutes.showCreate().url;


const toggleSorting = (columnId: string) => {
    const column = table.getColumn(columnId);
    column?.toggleSorting(column.getIsSorted() === 'asc');
};

const goToPage = (target: number) => {
    if (!meta.value) return;
    const next = Math.max(1, Math.min(target, meta.value.last_page));
    page.value = next;
};
</script>

<template>
  <Head title="Opportunities" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold">Opportunities</h1>
          <p class="text-sm text-muted-foreground">Manage volunteer opportunities.</p>
        </div>
        <Button
          as="a"
          :href="createUrl"
          variant="default"
        >
          Create opportunity
        </Button>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <div class="md:col-span-2">
          <Input
            v-model="globalSearch"
            placeholder="Global search"
          />
        </div>
        <div class="grid gap-3 md:grid-cols-2">
          <Input
            v-model="nameFilter"
            placeholder="Filter by name"
          />
          <Input
            v-model="descriptionFilter"
            placeholder="Filter by description"
          />
        </div>
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
                      <template v-if="header.column.getCanSort()">
                        <button
                          class="flex items-center gap-1"
                          type="button"
                          @click="toggleSorting(header.column.id)"
                        >
                          {{ header.column.columnDef.header as string }}
                          <span class="text-xs text-muted-foreground">
                            {{ header.column.getIsSorted() === 'asc' ? '▲' : header.column.getIsSorted() === 'desc' ? '▼' : '' }}
                          </span>
                        </button>
                      </template>
                      <template v-else>
                        {{ header.column.columnDef.header as string }}
                      </template>
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
                Loading opportunities...
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
                Something went wrong loading opportunities.
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
                      <Button
                        as-child
                        size="sm"
                        variant="secondary"
                      >
                        <Link :href="opportunitiesRoutes.show(row.original.id).url">
                          Edit
                        </Link>
                      </Button>
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
                No opportunities found yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta"
        class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
      >
        <div>
          Page {{ meta.current_page }} of {{ meta.last_page }}
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="secondary"
            size="sm"
            :disabled="meta.current_page <= 1 || isFetching"
            @click="goToPage(meta.current_page - 1)"
          >
            Previous
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :disabled="meta.current_page >= meta.last_page || isFetching"
            @click="goToPage(meta.current_page + 1)"
          >
            Next
          </Button>
        </div>
        <div>
          Showing {{ meta.from ?? 0 }}-{{ meta.to ?? 0 }} of {{ meta.total }} opportunities
        </div>
      </div>
    </div>
  </AppLayout>
</template>
