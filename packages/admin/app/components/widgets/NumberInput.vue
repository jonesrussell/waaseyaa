<script setup lang="ts">
import type { SchemaProperty } from '~/composables/useSchema'

defineProps<{
  modelValue: number | string
  label?: string
  description?: string
  required?: boolean
  disabled?: boolean
  schema?: SchemaProperty
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number] }>()

function onInput(event: Event) {
  const val = (event.target as HTMLInputElement).valueAsNumber
  emit('update:modelValue', Number.isNaN(val) ? 0 : val)
}
</script>

<template>
  <div class="field">
    <label v-if="label" class="field-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>
    <input
      type="number"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :min="schema?.minimum"
      :max="schema?.maximum"
      class="field-input"
      @input="onInput"
    >
    <p v-if="description" class="field-description">{{ description }}</p>
  </div>
</template>
