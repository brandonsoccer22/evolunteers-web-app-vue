<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { useVModel } from '@vueuse/core'

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes['class']
  modelModifiers?: {
    lazy?: boolean
  }
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const onInput = (event: Event) => {
  if (props.modelModifiers?.lazy) return
  const target = event.target as HTMLInputElement | null
  modelValue.value = (target?.value ?? '') as string | number
}

const onChange = (event: Event) => {
  const target = event.target as HTMLInputElement | null
  modelValue.value = (target?.value ?? '') as string | number
}
</script>

<template>
  <input
    :value="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
    @input="onInput"
    @change="onChange"
  >
</template>

<style scoped>
/* Improve visibility of date/time picker icons in dark mode */
:global(.dark input[type='date']::-webkit-calendar-picker-indicator),
:global(.dark input[type='time']::-webkit-calendar-picker-indicator) {
  filter: invert(1);
}
</style>
