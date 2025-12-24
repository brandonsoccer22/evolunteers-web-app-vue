<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { useQuery } from '@tanstack/vue-query';

import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import Input from '@/components/ui/input/Input.vue';
import VueSelect from '@/components/ui/select/VueSelect.vue';
import { useAxios } from '@/composables/useAxios';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue';
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
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
  field: FilterField | '';
  value: string;
  operator?: DateOperator;
}

const axios = useAxios();
const nextId = ref(1);
const filters = ref<FilterItem[]>(
  (props.data?.filters as FilterItem[] | undefined)?.map((item) => ({
    ...item,
    id: nextId.value++,
    field: (item.field as FilterField) ?? '',
    operator: (item.operator as DateOperator) ?? 'eq',
  })) ?? [{ id: nextId.value++, field: 'name', value: '', operator: 'eq' }],
);
const page = ref(1);
const perPage = ref(12);

const fieldOptions: { value: FilterField; label: string; placeholder: string }[] = [
  { value: 'name', label: 'Name / description', placeholder: 'Search by title or description' },
  { value: 'organization', label: 'Organization', placeholder: 'Org name' },
  { value: 'start_date', label: 'Start date', placeholder: 'On or after date' },
  { value: 'tag', label: 'Tag', placeholder: 'Comma separated tags' },
];

const normalizedFilters = computed(() =>
  filters.value
    .filter((filter) => filter.field !== '')
    .map((filter) => ({
      field: filter.field as FilterField,
      value: filter.value?.toString().trim() ?? '',
      operator: filter.field === 'start_date' ? filter.operator ?? 'eq' : undefined,
    }))
    .filter((filter) => filter.value !== ''),
);

watch(
  () => normalizedFilters.value,
  () => {
    page.value = 1;
  },
  { deep: true },
);

const debouncedFilters = ref(normalizedFilters.value);
const applyFilters = useDebounceFn(
  (value: typeof normalizedFilters.value, immediate = false) => {
    debouncedFilters.value = value;
    if (immediate) {
      refetch();
    }
  },
  350,
  { maxWait: 800 },
);

watch(
  () => normalizedFilters.value,
  (current, previous) => {
    const immediate = isNonTextChange(previous, current);
    applyFilters(current, immediate);
  },
  { deep: true },
);

const filtersKey = computed(() => JSON.stringify(debouncedFilters.value));

const { data, isFetching, isError, refetch } = useQuery({
  queryKey: computed(() => ['public-opportunities', page.value, perPage.value, filtersKey.value]),
  queryFn: async () => {
    const response = await axios.get('/opportunities', {
      params: {
        filters: debouncedFilters.value,
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
const organizationOptions = computed(() => {
  const names = new Set<string>();
  results.value.forEach((item) => {
    item.organizations?.forEach((org) => {
      if (org?.name) names.add(org.name);
    });
  });
  return Array.from(names).map((name) => ({ label: name, value: name }));
});
const tagOptions = computed(() => {
  const names = new Set<string>();
  results.value.forEach((item) => {
    item.tags?.forEach((tag) => {
      if (tag?.name) names.add(tag.name);
    });
  });
  return Array.from(names).map((name) => ({ label: name, value: name }));
});

const addFilter = () => {
  filters.value.push({
    id: nextId.value++,
    field: '',
    value: '',
    operator: 'eq',
  });
};

const removeFilter = (id: number) => {
  if (filters.value.length === 1) {
    filters.value = [{ id: nextId.value++, field: 'name', value: '', operator: 'eq' }];
    return;
  }
  filters.value = filters.value.filter((filter) => filter.id !== id);
};

const updateFilterField = (id: number, field: FilterField | '') => {
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

const selectedOpportunity = ref<Opportunity | null>(null);
const openModal = (item: Opportunity) => {
  selectedOpportunity.value = item;
};
const closeModal = () => {
  selectedOpportunity.value = null;
};

function isNonTextChange(prev: typeof normalizedFilters.value, next: typeof normalizedFilters.value): boolean {
  if (!prev || !next) return true;
  if (prev.length !== next.length) return true;
  for (let i = 0; i < next.length; i += 1) {
    const a = prev[i];
    const b = next[i];
    if (!a || !b) return true;
    if (a.field !== b.field) return true;
    if (a.operator !== b.operator) return true;
    if (a.field === 'name' || a.field === 'description') {
      if (a.value !== b.value) continue;
    } else if (a.value !== b.value) {
      return true;
    }
  }
  return false;
}
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

      <div class="flex flex-col gap-3">
        <div
          v-for="filter in filters"
          :key="filter.id"
          class="flex flex-col gap-2 rounded-xl border border-white/10 bg-white/5 p-3"
        >
          <div
            :class="filter.field === 'start_date' ? 'grid w-full grid-cols-[2fr,1fr,1fr] items-center gap-2' : 'grid w-full grid-cols-[3fr,1fr] items-center gap-2'"
          >
            <div class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span class="sr-only">Field</span>
              <select
                v-model="filter.field"
                class="rounded-md border border-white/20 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-orange-300 focus:bg-slate-900 focus:outline-none"
                @change="updateFilterField(filter.id, filter.field)"
              >
                <option disabled value="">Search by...</option>
                <option
                  v-for="option in fieldOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div v-if="filter.field === 'start_date'" class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span class="sr-only">Date comparison</span>
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
            </div>

            <div class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span class="sr-only">Value</span>
              <template v-if="filter.field === 'start_date'">
                <Input
                  v-model="filter.value"
                  type="date"
                  class="bg-slate-950/60 text-white focus-visible:ring-orange-300"
                />
              </template>
              <template v-else-if="filter.field === 'organization'">
                <VueSelect
                  v-model="filter.value"
                  :options="organizationOptions"
                  placeholder="Select organization"
                  class="bg-slate-950/60 text-white"
                  @update:modelValue="() => applyFilters(normalizedFilters.value, true)"
                />
              </template>
              <template v-else-if="filter.field === 'tag'">
                <VueSelect
                  v-model="filter.value"
                  :options="tagOptions"
                  placeholder="Select tag"
                  class="bg-slate-950/60 text-white"
                  @update:modelValue="() => applyFilters(normalizedFilters.value, true)"
                />
              </template>
              <template v-else>
                <Input
                  v-model="filter.value"
                  :placeholder="fieldOptions.find((opt) => opt.value === filter.field)?.placeholder || 'Search...'"
                  class="bg-slate-950/60 text-white focus-visible:ring-orange-300"
                />
              </template>
            </div>
          </div>
          <div class="flex justify-end">
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
        <div class="flex justify-end">
          <Button size="sm" variant="secondary" @click="addFilter">
            Add condition
          </Button>
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
          role="button"
          tabindex="0"
          @click="openModal(item)"
          @keydown.enter.prevent="openModal(item)"
          @keydown.space.prevent="openModal(item)"
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

      <Dialog :open="!!selectedOpportunity" @update:open="(open) => !open && closeModal()">
        <DialogContent class="max-w-2xl bg-slate-950 text-slate-50">
          <DialogHeader>
            <DialogTitle class="text-2xl font-semibold">{{ selectedOpportunity?.name }}</DialogTitle>
            <DialogDescription class="text-sm text-slate-300">
              {{ selectedOpportunity?.organizations?.[0]?.name ?? 'Open opportunity' }}
            </DialogDescription>
          </DialogHeader>
          <div class="mt-4 space-y-4">
            <p class="text-sm leading-relaxed text-slate-200">
              {{ selectedOpportunity?.description || 'No description provided yet.' }}
            </p>
            <div class="flex flex-wrap gap-3 text-sm text-slate-200">
              <span class="rounded-full bg-orange-300/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-orange-200">
                Starts: {{ formatDate(selectedOpportunity?.start_date) }}
              </span>
              <span
                v-for="org in selectedOpportunity?.organizations ?? []"
                :key="org.id"
                class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white"
              >
                {{ org.name }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in selectedOpportunity?.tags ?? []"
                :key="tag.id"
                class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white"
              >
                {{ tag.name }}
              </span>
              <span v-if="!selectedOpportunity?.tags?.length" class="text-xs text-slate-300/80">No tags yet</span>
            </div>
          </div>
          <div class="mt-6 flex justify-end">
            <Button variant="secondary" @click="closeModal">
              Close
            </Button>
          </div>
        </DialogContent>
      </Dialog>

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
