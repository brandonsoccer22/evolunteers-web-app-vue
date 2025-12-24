<script setup lang="ts">
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions, TransitionRoot } from '@headlessui/vue';
import { useQuery } from '@tanstack/vue-query';
import { useDebounceFn, useVModel } from '@vueuse/core';
import { ChevronsUpDown, Loader2, X } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';

import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: unknown | unknown[] | null;
        fetchOptions: (term: string) => Promise<unknown[]>;
        queryKeyBase?: readonly unknown[] | string;
        placeholder?: string;
        labelKey?: string;
        valueKey?: string;
        minQueryLength?: number;
        debounce?: number;
        disabled?: boolean;
        clearable?: boolean;
        noOptionsText?: string;
        loadingText?: string;
        idleText?: string;
        errorText?: string;
        class?: HTMLAttributes['class'];
        maxOptionsHeightClass?: string;
        staleTime?: number;
        multiple?: boolean;
        disabledIds?: unknown[];
    }>(),
    {
        placeholder: 'Search...',
        labelKey: 'label',
        valueKey: 'value',
        minQueryLength: 2,
        debounce: 250,
        clearable: true,
        disabled: false,
        noOptionsText: 'No results found',
        loadingText: 'Loading...',
        idleText: 'Start typing to search',
        errorText: 'Something went wrong',
        maxOptionsHeightClass: 'max-h-60',
        staleTime: 60_000,
        multiple: false,
        disabledIds: () => [],
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', value: unknown | null): void;
    (e: 'search', term: string): void;
}>();

const selected = useVModel(props, 'modelValue', emits, {
    defaultValue: props.multiple ? [] : null,
});

const searchTerm = ref('');
const debouncedSearch = ref('');
const hasSelection = computed(() => {
    if (props.multiple) return Array.isArray(selected.value) && selected.value.length > 0;
    return selected.value !== null && selected.value !== undefined;
});

const updateDebounced = useDebounceFn((term: string) => {
    debouncedSearch.value = term.trim();
}, props.debounce);

watch(
    () => searchTerm.value,
    (term) => {
        updateDebounced(term);
        emits('search', term);
    },
);

const isReady = computed(() => debouncedSearch.value.length >= props.minQueryLength);

const queryKey = computed(() => {
    const base = props.queryKeyBase ?? 'async-select';
    const baseArray = typeof base === 'string' ? [base] : Array.isArray(base) ? [...base] : [base];
    return [...baseArray, debouncedSearch.value];
});

const { data, isFetching, isError, error, refetch } = useQuery({
    queryKey,
    queryFn: () => props.fetchOptions(debouncedSearch.value),
    enabled: isReady,
    staleTime: props.staleTime,
});

const options = computed(() => (Array.isArray(data.value) ? data.value : []));
const visibleOptions = computed(() => (isReady.value ? options.value : []));

const getLabel = (option: unknown): string => {
    if (option == null) return '';
    if (typeof props.labelKey === 'string' && typeof option === 'object' && props.labelKey in option) {
        const value = (option as Record<string, unknown>)[props.labelKey];
        return value == null ? '' : String(value);
    }
    return String(option);
};

const getValue = (option: unknown): unknown => {
    if (option && typeof option === 'object' && props.valueKey in (option as Record<string, unknown>)) {
        return (option as Record<string, unknown>)[props.valueKey];
    }
    return option;
};

const compareOptions = (a: unknown, b: unknown) => getValue(a) === getValue(b);
const disabledIdSet = computed(() => new Set(props.disabledIds ?? []));
const isOptionSelected = (option: unknown) => {
    if (props.multiple && Array.isArray(selected.value)) {
        return selected.value.some((item) => compareOptions(item, option));
    }
    return compareOptions(selected.value, option);
};
const isOptionDisabled = (option: unknown) => disabledIdSet.value.has(getValue(option)) || isOptionSelected(option);

const displayValue = (value: unknown | unknown[]) => {
    if (props.multiple) {
        if (searchTerm.value) return searchTerm.value;
        if (!Array.isArray(value)) return '';
        return value.map((option) => getLabel(option)).filter(Boolean).join(', ');
    }
    return getLabel(value);
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement | null;
    searchTerm.value = target?.value ?? '';
};

const clearSelection = () => {
    searchTerm.value = '';
    debouncedSearch.value = '';
    selected.value = props.multiple ? [] : null;
};

const errorMessage = computed(() => {
    if (!error.value) return props.errorText;
    return error.value instanceof Error ? error.value.message : String(error.value);
});
</script>

<template>
  <Combobox
    v-model="selected"
    :by="compareOptions"
    :disabled="props.disabled"
    :multiple="props.multiple"
  >
    <div :class="cn('relative w-full', props.class)">
      <ComboboxInput
        class="border-input ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring h-10 w-full rounded-md border bg-transparent px-3 py-2 pr-12 text-sm outline-none transition-[border,box-shadow] focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :display-value="displayValue"
        :placeholder="props.placeholder"
        autocomplete="off"
        @input="handleInput"
      />
      <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center gap-1">
        <button
          v-if="props.clearable && hasSelection"
          type="button"
          class="pointer-events-auto text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          @click.stop="clearSelection"
        >
          <X class="h-4 w-4" />
        </button>
        <ComboboxButton class="pointer-events-auto text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
          <ChevronsUpDown class="h-4 w-4" />
        </ComboboxButton>
      </div>

      <TransitionRoot
        leave="transition ease-in duration-100"
        leave-from="opacity-100"
        leave-to="opacity-0"
        @after-leave="searchTerm = ''"
      >
        <ComboboxOptions
          class="bg-popover text-popover-foreground absolute z-50 mt-2 w-full overflow-hidden rounded-md border shadow-md focus:outline-none"
          :class="props.maxOptionsHeightClass"
        >
          <div
            v-if="!isReady"
            class="px-3 py-2 text-sm text-muted-foreground"
          >
            <slot
              name="idle"
              :term="searchTerm"
            >
              {{ props.idleText }}
            </slot>
          </div>

          <div
            v-else-if="isFetching"
            class="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground"
          >
            <slot name="loading">
              <Loader2 class="h-4 w-4 animate-spin" />
              <span>{{ props.loadingText }}</span>
            </slot>
          </div>

          <div
            v-else-if="isError"
            class="flex items-center justify-between gap-3 px-3 py-2 text-sm text-destructive"
          >
            <slot
              name="error"
              :error="error?.value"
              :retry="refetch"
            >
              <span class="truncate">{{ errorMessage }}</span>
              <button
                type="button"
                class="text-xs font-medium underline underline-offset-2"
                @click.stop="refetch()"
              >
                Retry
              </button>
            </slot>
          </div>

          <template v-else-if="visibleOptions.length">
            <ComboboxOption
              v-for="option in visibleOptions"
              :key="getValue(option) ?? getLabel(option)"
              v-slot="{ active, selected: optionSelected }"
              :value="option"
              :disabled="isOptionDisabled(option)"
            >
              <div
                :class="cn(
                  'flex cursor-pointer select-none items-center gap-2 px-3 py-2 text-sm',
                  active && 'bg-accent text-accent-foreground',
                  optionSelected && 'font-medium',
                  isOptionDisabled(option) && 'opacity-60',
                )"
              >
                <slot
                  name="option"
                  :option="option"
                  :active="active"
                  :selected="optionSelected"
                >
                  {{ getLabel(option) }}
                </slot>
              </div>
            </ComboboxOption>
          </template>

          <div
            v-else
            class="px-3 py-2 text-sm text-muted-foreground"
          >
            <slot name="empty">
              {{ props.noOptionsText }}
            </slot>
          </div>
        </ComboboxOptions>
      </TransitionRoot>
    </div>
  </Combobox>
</template>
