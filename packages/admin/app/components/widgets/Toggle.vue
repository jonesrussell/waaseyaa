<script setup lang="ts">
import type { SchemaProperty } from '~/composables/useSchema'

defineProps<{
  modelValue: boolean
  label?: string
  description?: string
  required?: boolean
  disabled?: boolean
  schema?: SchemaProperty
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
  <div class="field field-toggle">
    <label class="field-label toggle-label">
      <input
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        class="toggle-checkbox"
        @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
      >
      <span>{{ label }}</span>
      <span v-if="required" class="required">*</span>
    </label>
    <p v-if="description" class="field-description">{{ description }}</p>
  </div>
</template>
