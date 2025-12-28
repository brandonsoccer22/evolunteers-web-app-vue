<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
import type { Opportunity, Tag } from '@/types';
import { home, logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

interface Props {
  data?: {
    filters?: { field: string; value: string }[];
    opportunities?: Opportunity[];
    tags?: Tag[];
    meta?: Record<string, unknown>;
  };
}

const props = defineProps<Props>();

type FilterField = 'name' | 'organization' | 'start_date' | 'tag';
type DateOperator = 'eq' | 'gt' | 'gte' | 'lt' | 'lte' | 'neq';
interface FilterItem {
  id: number;
  field: FilterField | '';
  value: string | number | Record<string, unknown> | null;
  operator?: DateOperator;
}

const axios = useAxios();
const nextId = ref(1);
const initialFilters = (props.data?.filters as FilterItem[] | undefined)?.map((item) => ({
  ...item,
  id: nextId.value++,
  field: (item.field as FilterField) ?? '',
  operator: (item.operator as DateOperator) ?? 'eq',
})) ?? [];

if (!initialFilters.length) {
  initialFilters.push({ id: nextId.value++, field: 'name', value: '', operator: 'eq' });
}

const filters = ref<FilterItem[]>(initialFilters);
const page = ref(1);
const perPage = ref(12);

const normalizeSelectValue = (value: unknown): string | number => {
  if (value && typeof value === 'object' && 'value' in (value as Record<string, unknown>)) {
    const val = (value as Record<string, unknown>).value;
    return (val as string | number) ?? '';
  }
  return (value as string | number) ?? '';
};

const fieldOptions: { value: FilterField; label: string; placeholder: string }[] = [
  { value: 'name', label: 'Name / description', placeholder: 'Search by name or description...' },
  { value: 'organization', label: 'Organization', placeholder: 'Org name' },
  { value: 'start_date', label: 'Start date', placeholder: 'On or after date' },
  { value: 'tag', label: 'Tag', placeholder: 'Comma separated tags' },
];

const filterValueForQuery = (value: unknown): string => {
  const normalized = normalizeSelectValue(value);
  if (normalized == null) return '';
  return typeof normalized === 'string' ? normalized.trim() : String(normalized);
};

const normalizedFilters = computed(() =>
  filters.value
    .filter((filter) => filter.field !== '')
    .map((filter) => ({
      field: filter.field as FilterField,
      value: filterValueForQuery(filter.value),
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

const filtersKey = computed(() => JSON.stringify(normalizedFilters.value));

const { data, isFetching, isError, refetch } = useQuery({
  queryKey: computed(() => ['public-opportunities', page.value, perPage.value, filtersKey.value]),
  queryFn: async () => {
    const response = await axios.get('/opportunities', {
      params: {
        filters: normalizedFilters.value,
        page: page.value,
        per_page: perPage.value,
      },
    });

    return {
      data: response.data?.data ?? response.data?.opportunities ?? response.data ?? [],
      meta: response.data?.meta ?? {},
    };
  },
  refetchOnMount: 'always',
  initialData: () => ({
    data: props.data?.opportunities ?? [],
    meta: props.data?.meta ?? {},
  }),
});
// Initial fetch will run via query when component mounts and queryKey is ready

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
  const tags = new Map<number, string>();
  props.data?.tags?.forEach((tag) => {
    if (tag?.id != null && tag?.name) tags.set(tag.id, tag.name);
  });
  results.value.forEach((item) => {
    item.tags?.forEach((tag) => {
      if (tag?.id != null && tag?.name) tags.set(tag.id, tag.name);
    });
  });
  return Array.from(tags.entries()).map(([id, name]) => ({ label: name, value: id }));
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

const updateFilterValue = (id: number, value: unknown, immediate = true) => {
  const target = filters.value.find((filter) => filter.id === id);
  if (!target) return;
  target.value = value as Record<string, unknown> | string | number | null;
};

const updateFilterField = (id: number, field: FilterField | '') => {
  const target = filters.value.find((filter) => filter.id === id);
  if (!target) return;
  target.field = field;
  // Clear previous value whenever the field changes so stale select objects don't bleed into other inputs
  target.value = '';
  target.operator = field === 'start_date' ? 'eq' : undefined;
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
  const dateOnlyMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  const date = dateOnlyMatch
    ? new Date(Date.UTC(Number(dateOnlyMatch[1]), Number(dateOnlyMatch[2]) - 1, Number(dateOnlyMatch[3])))
    : new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(date);
};

const goToPage = (target: number) => {
  const current = meta.value?.current_page ?? 1;
  const last = meta.value?.last_page ?? 1;
  const next = Math.max(1, Math.min(target, last));
  if (next !== current) {
    page.value = next;
    refetch();
  }
};

const selectedOpportunity = ref<Opportunity | null>(null);
const showLogoutSubmitting = ref(false);
const submitLogout = async () => {
  showLogoutSubmitting.value = true;
  try {
    await axios.post(logout().url);
    window.location.href = home().url;
  } finally {
    showLogoutSubmitting.value = false;
  }
};

const openModal = (item: Opportunity) => {
  selectedOpportunity.value = item;
};
const closeModal = () => {
  selectedOpportunity.value = null;
};
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-slate-50">
    <Head title="Opportunities" />
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 pb-12 pt-10 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Button as="a" :href="home().url" size="sm" variant="ghost" class="rounded-full bg-white/10 px-4 text-white hover:bg-white/20">
            eVol
          </Button>
          <Button size="sm" variant="secondary" class="rounded-full px-4" :disabled="showLogoutSubmitting" @click="submitLogout">
            {{ showLogoutSubmitting ? 'Logging out...' : 'Logout' }}
          </Button>
          <Button as="a" :href="editProfile().url" size="icon" variant="ghost" class="rounded-full bg-white/10 text-white hover:bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 20.25a7.5 7.5 0 0115 0" />
            </svg>
          </Button>
        </div>
      </div>
      <div class="flex flex-col gap-3">
        <p class="text-sm uppercase tracking-[0.24em] text-orange-300">Find your next chance to help</p>
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 class="text-3xl font-semibold text-white sm:text-4xl">Opportunities</h1>
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
          v-for="(filter, index) in filters"
          :key="filter.id"
          class="flex flex-col gap-2 rounded-xl border border-white/10 bg-white/5 p-3"
        >
          <div
            :class="filter.field === 'start_date' ? 'grid w-full grid-cols-1 items-center gap-2 sm:grid-cols-[2fr_1fr_1fr]' : 'grid w-full grid-cols-1 items-center gap-2 sm:grid-cols-[3fr_1fr]'"
          >
            <div class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span class="sr-only">Value</span>
              <template v-if="filter.field === 'start_date'">
                <Input
                  v-model.lazy="(filter.value as string | number)"
                  type="date"
                  class="bg-slate-950/60! text-white focus-visible:ring-orange-300"
                />
              </template>
              <template v-else-if="filter.field === 'organization'">
                <VueSelect
                  v-model="filter.value"
                  :options="organizationOptions"
                  placeholder="Select organization"
                  class="bg-slate-950/60 border-input text-white rounded-md"
                  @update:modelValue="(val: any) => updateFilterValue(filter.id, val, true)"
                />
              </template>
              <template v-else-if="filter.field === 'tag'">
                <VueSelect
                  v-model="filter.value"
                  :options="tagOptions"
                  placeholder="Select tag"
                  class="bg-slate-950/60 border-input text-white rounded-md"
                  @update:modelValue="(val) => updateFilterValue(filter.id, val, true)"
                />
              </template>
              <template v-else>
                <Input
                  v-model="(filter.value as string | number)"
                  :placeholder="fieldOptions.find((opt: any) => opt.value === filter.field)?.placeholder || 'Search...'"
                  class="bg-slate-950/60! border-input text-white focus-visible:ring-orange-300"
                />
              </template>
            </div>

             <div v-if="filter.field === 'start_date'" class="flex flex-col gap-1 text-sm font-medium text-slate-100">
              <span class="sr-only">Date comparison</span>
              <select
                v-model="filter.operator"
                class="rounded-md border border-input bg-slate-950/60 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-orange-300 focus:bg-slate-900 focus:outline-none"
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
              <span class="sr-only">Field</span>
              <select
                v-model="filter.field"
                class="rounded-md border border-input bg-slate-950/60 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-orange-300 focus:bg-slate-900 focus:outline-none"
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

          </div>
          <div class="flex justify-end">
            <Button
              v-if="index > 0"
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
            <div class="flex max-w-[45%] flex-wrap justify-end gap-1">
              <span
                v-for="tag in (item.tags ?? []).slice(0, 3)"
                :key="tag.id"
                class="rounded-full bg-orange-300/20 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-orange-200"
              >
                {{ tag.name }}
              </span>
            </div>
          </div>
          <p class="mt-2 line-clamp-4 text-sm text-slate-200/80">
            {{ item.description || 'No description provided yet.' }}
          </p>
          <div class="mt-auto space-y-2 pt-4">
            <div class="flex items-center justify-between text-sm text-orange-100">
              <span class="font-semibold text-white">Starts</span>
              <span>{{ formatDate(item.start_date) }}</span>
            </div>
            <p class="line-clamp-1 text-xs text-slate-200/80">
              {{ item.organizations?.map((o) => o.name).filter(Boolean).join(', ') || 'Open opportunity' }}
            </p>
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
