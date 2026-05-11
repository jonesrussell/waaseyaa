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

const inputType = computed(() => {
  const widget = props.schema?.['x-widget'] ?? 'text'
  if (widget === 'email') return 'email'
  if (widget === 'url') return 'url'
  return 'text'
})
</script>

<template>
  <div class="field">
    <label v-if="label" class="field-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>
    <input
      :type="inputType"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :maxlength="schema?.maxLength"
      :aria-label="label"
      class="field-input"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <p v-if="description" class="field-description">{{ description }}</p>
  </div>
</template>
