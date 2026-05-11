<script setup lang="ts">
import type { SchemaProperty } from '~/composables/useSchema'

const props = defineProps<{
  modelValue: string
  label?: string
  description?: string
  required?: boolean
  disabled?: boolean
  schema?: SchemaProperty
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

// datetime-local inputs require YYYY-MM-DDTHH:MM:SS without timezone offset.
// The API returns ISO 8601 with offset (e.g. "2026-03-08T15:52:48+00:00").
const localValue = computed(() => {
  if (!props.modelValue) return ''
  // Strip timezone offset or trailing Z, keep only YYYY-MM-DDTHH:MM:SS
  return props.modelValue.replace(/([+-]\d{2}:\d{2}|Z)$/, '').slice(0, 19)
})
</script>

<template>
  <div class="field">
    <label v-if="label" class="field-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>
    <input
      type="datetime-local"
      :value="localValue"
      :required="required"
      :disabled="disabled"
      class="field-input"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <p v-if="description" class="field-description">{{ description }}</p>
  </div>
</template>
