<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useQuery } from '@tanstack/vue-query';

import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import Input from '@/components/ui/input/Input.vue';
import { useAxios } from '@/composables/useAxios';
import type { Opportunity } from '@/types';

interface Props {
  data?: {
    filters?: { field: string; value: string }[];
    opportunities?: Opportunity[];
    meta?: Record<string, unknown>;
  };
}

const props = defineProps<Props>();

type FilterField = 'name' | 'organization' | 'start_date' | 'tag';
type DateOperator = 'eq' | 'gt' | 'gte' | 'lt' | 'lte' | 'neq';
interface FilterItem {
  id: number;
  field: FilterField;
  value: string;
  operator?: DateOperator;
}

const axios = useAxios();
const nextId = ref(1);
const filters = ref<FilterItem[]>(
  (props.data?.filters as FilterItem[] | undefined)?.map((item) => ({
    ...item,
    id: nextId.value++,
    field: (item.field as FilterField) ?? 'name',
    operator: (item.operator as DateOperator) ?? 'eq',
  })) ?? [{ id: nextId.value++, field: 'name', value: '' }],
);
const page = ref(1);
const perPage = ref(12);

const fieldOptions: { value: FilterField; label: string; placeholder: string }[] = [
  { value: 'name', label: 'Name / description', placeholder: 'Search by title or description' },
  { value: 'organization', label: 'Organization', placeholder: 'Org name' },
  { value: 'start_date', label: 'Start date', placeholder: 'On or after date' },
  { value: 'tag', label: 'Tag', placeholder: 'Comma separated tags' },
];

const sanitizedFilters = computed(() =>
  filters.value
    .map((filter) => ({
      field: filter.field === 'name' ? 'name' : filter.field,
      value: filter.value?.toString().trim() ?? '',
      operator: filter.field === 'start_date' ? filter.operator ?? 'eq' : undefined,
    }))
    .filter((filter) => filter.value !== ''),
);

watch(
  () => sanitizedFilters.value,
  () => {
    page.value = 1;
  },
  { deep: true },
);

const filtersKey = computed(() => JSON.stringify(sanitizedFilters.value));

const { data, isFetching, isError, refetch } = useQuery({
  queryKey: computed(() => ['public-opportunities', page.value, perPage.value, filtersKey.value]),
  queryFn: async () => {
    const response = await axios.get('/opportunities', {
      params: {
        filters: sanitizedFilters.value,
        page: page.value,
        per_page: perPage.value,
      },
    });

    return {
      data: response.data?.data ?? response.data?.opportunities ?? response.data ?? [],
      meta: response.data?.meta ?? {},
    };
  },
  keepPreviousData: true,
  staleTime: 30_000,
  initialData: () => ({
    data: props.data?.opportunities ?? [],
    meta: props.data?.meta ?? {},
  }),
});

const results = computed<Opportunity[]>(() => (data.value?.data as Opportunity[]) ?? []);
const meta = computed(() => (data.value?.meta ?? {}) as Record<string, any>);

const addFilter = () => {
  filters.value.push({
    id: nextId.value++,
    field: 'name',
    value: '',
  });
};

const removeFilter = (id: number) => {
  if (filters.value.length === 1) {
    filters.value = [{ id: nextId.value++, field: 'name', value: '' }];
    return;
  }
  filters.value = filters.value.filter((filter) => filter.id !== id);
};

const updateFilterField = (id: number, field: FilterField) => {
  const target = filters.value.find((filter) => filter.id === id);
  if (!target) return;
  target.field = field;
  if (field === 'start_date') {
    target.value = '';
    target.operator = 'eq';
  } else {
    target.operator = undefined;
  }
};

const dateOperators: { value: DateOperator; label: string }[] = [
  { value: 'eq', label: 'Equal to' },
  { value: 'gt', label: 'Greater than' },
  { value: 'gte', label: 'Greater or equal' },
  { value: 'lt', label: 'Less than' },
  { value: 'lte', label: 'Less or equal' },
  { value: 'neq', label: 'Not equal' },
];

const formatDate = (value?: string | null) => {
  if (!value) return 'Date not set';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
};

const goToPage = (target: number) => {
  const current = meta.value?.current_page ?? 1;
  const last = meta.value?.last_page ?? 1;
  const next = Math.max(1, Math.min(target, last));
  if (next !== current) page.value = next;
};
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-slate-50">
    <Head title="Opportunities" />
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 pb-12 pt-10 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-3">
        <p class="text-sm uppercase tracking-[0.24em] text-orange-300">Find your next chance to help</p>
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 class="text-3xl font-semibold text-white sm:text-4xl">Opportunities dashboard</h1>
            <p class="text-base text-slate-200/80 sm:text-lg">
              Combine multiple filters, search through names and descriptions, and skim what starts next.
            </p>
          </div>
          <Button variant="secondary" size="sm" @click="refetch">
            Refresh
          </Button>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-xl backdrop-blur">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-white">Search conditions</h2>
          <Button size="sm" variant="secondary" @click="addFilter">
            Add condition
          </Button>
        </div>
        <div class="mt-4 space-y-3">
          <div
            v-for="filter in filters"
            :key="filter.id"
            class="grid gap-2 rounded-xl border border-white/10 bg-white/5 p-3 sm:grid-cols-[180px,1fr,auto]"
          >
            <label class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              Field
              <select
                v-model="filter.field"
                class="rounded-md border border-white/20 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-orange-300 focus:bg-slate-900 focus:outline-none"
                @change="updateFilterField(filter.id, filter.field)"
              >
                <option
                  v-for="option in fieldOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </label>

            <div class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span>Value</span>
              <Input
                v-if="filter.field !== 'start_date'"
                v-model="filter.value"
                :placeholder="fieldOptions.find((opt) => opt.value === filter.field)?.placeholder"
                class="bg-slate-950/60 text-white focus-visible:ring-orange-300"
              />
              <div v-else class="grid grid-cols-[160px,1fr] gap-2">
                <select
                  v-model="filter.operator"
                  class="rounded-md border border-white/20 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-orange-300 focus:bg-slate-900 focus:outline-none"
                >
                  <option
                    v-for="op in dateOperators"
                    :key="op.value"
                    :value="op.value"
                  >
                    {{ op.label }}
                  </option>
                </select>
                <Input
                  v-model="filter.value"
                  type="date"
                  class="bg-slate-950/60 text-white focus-visible:ring-orange-300"
                />
              </div>
              <p class="text-xs font-normal text-slate-300/80" v-if="filter.field === 'tag'">
                Separate multiple tags with commas.
              </p>
            </div>

            <div class="flex items-start justify-end">
              <Button
                size="sm"
                variant="ghost"
                class="text-slate-200 hover:bg-white/10 hover:text-white"
                @click="removeFilter(filter.id)"
              >
                Remove
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="text-sm text-slate-200/80">
          <span class="font-medium text-white">{{ meta?.total ?? results.length }}</span>
          opportunities
        </div>
        <div class="flex items-center gap-2 text-sm text-slate-200/80">
          <Button
            variant="secondary"
            size="sm"
            :disabled="(meta?.current_page ?? 1) <= 1 || isFetching"
            @click="goToPage((meta?.current_page ?? 1) - 1)"
          >
            Prev
          </Button>
          <span class="text-white">
            Page {{ meta?.current_page ?? 1 }} / {{ meta?.last_page ?? 1 }}
          </span>
          <Button
            variant="secondary"
            size="sm"
            :disabled="(meta?.current_page ?? 1) >= (meta?.last_page ?? 1) || isFetching"
            @click="goToPage((meta?.current_page ?? 1) + 1)"
          >
            Next
          </Button>
        </div>
      </div>

      <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        data-testid="opportunity-grid"
      >
        <Card
          v-for="item in results"
          :key="item.id"
          class="group relative flex aspect-square flex-col overflow-hidden border-white/10 bg-white/5 p-5 transition hover:-translate-y-1 hover:border-orange-300/70 hover:shadow-[0_10px_40px_rgba(255,112,66,0.25)]"
        >
          <div class="flex items-start justify-between gap-3">
            <h3 class="line-clamp-2 text-lg font-semibold text-white">{{ item.name }}</h3>
            <span class="rounded-full bg-orange-300/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-orange-200">
              {{ item.organizations?.[0]?.name ?? 'Open' }}
            </span>
          </div>
          <p class="mt-2 line-clamp-4 text-sm text-slate-200/80">
            {{ item.description || 'No description provided yet.' }}
          </p>
          <div class="mt-auto space-y-2 pt-4">
            <div class="flex items-center justify-between text-sm text-orange-100">
              <span class="font-semibold text-white">Starts</span>
              <span>{{ formatDate(item.start_date) }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in item.tags ?? []"
                :key="tag.id"
                class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white"
              >
                {{ tag.name }}
              </span>
              <span v-if="!item.tags?.length" class="text-xs text-slate-300/80">No tags yet</span>
            </div>
          </div>
        </Card>
      </div>

      <div
        v-if="isFetching"
        class="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200/80"
      >
        Loading opportunities...
      </div>
      <div
        v-else-if="isError"
        class="rounded-lg border border-red-400/50 bg-red-950/60 px-4 py-3 text-sm text-red-100"
      >
        Something went wrong while searching. Please try again.
      </div>
      <div
        v-else-if="!results.length"
        class="rounded-lg border border-white/10 bg-white/5 px-4 py-6 text-center text-sm text-slate-200/80"
      >
        No opportunities match these conditions yet. Try loosening the filters.
      </div>
    </div>
  </div>
</template>
