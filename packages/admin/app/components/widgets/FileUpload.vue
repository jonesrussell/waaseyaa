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

const uploading = ref(false)
const progress = ref(0)
const errorMessage = ref('')
const previewUrl = ref('')

let objectUrl: string | null = null

function setPreviewFromFile(file: globalThis.File) {
  if (!file.type.startsWith('image/')) {
    clearPreview()
    return
  }

  if (objectUrl !== null) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = null
  }

  objectUrl = URL.createObjectURL(file)
  previewUrl.value = objectUrl
}

function clearPreview() {
  if (objectUrl !== null) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = null
  }
  previewUrl.value = ''
}

onBeforeUnmount(() => {
  clearPreview()
})

function uploadFile(file: globalThis.File) {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('name', file.name)
  formData.append('bundle', file.type.startsWith('image/') ? 'image' : 'file')

  uploading.value = true
  progress.value = 0
  errorMessage.value = ''

  const xhr = new XMLHttpRequest()
  xhr.open('POST', '/api/media/upload', true)
  xhr.setRequestHeader('Accept', 'application/vnd.api+json')
  xhr.withCredentials = true

  xhr.upload.addEventListener('progress', (event) => {
    if (!event.lengthComputable || event.total <= 0) {
      return
    }
    progress.value = Math.round((event.loaded / event.total) * 100)
  })

  xhr.addEventListener('load', () => {
    uploading.value = false

    if (xhr.status < 200 || xhr.status >= 300) {
      errorMessage.value = 'Upload failed'
      return
    }

    try {
      const payload = JSON.parse(xhr.responseText ?? '{}')
      const attributes = payload?.data?.attributes ?? {}
      const value = String(attributes.file_url ?? attributes.file_uri ?? '')
      if (value === '') {
        errorMessage.value = 'Upload did not return a usable file value'
        return
      }
      emit('update:modelValue', value)
    } catch {
      errorMessage.value = 'Upload response was invalid'
    }
  })

  xhr.addEventListener('error', () => {
    uploading.value = false
    errorMessage.value = 'Upload failed'
  })

  xhr.send(formData)
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) {
    return
  }

  setPreviewFromFile(file)
  uploadFile(file)
}
</script>

<template>
  <div class="field">
    <label v-if="label" class="field-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>

    <input
      type="file"
      class="field-input"
      :disabled="disabled || uploading"
      @change="onFileChange"
    >

    <div v-if="uploading" class="field-upload-progress">
      <progress :value="progress" max="100" />
      <span>{{ progress }}%</span>
    </div>

    <img
      v-if="previewUrl"
      :src="previewUrl"
      alt="Image preview"
      class="field-upload-preview"
    >

    <p v-if="modelValue" class="field-description">
      {{ modelValue }}
    </p>

    <p v-if="description" class="field-description">{{ description }}</p>
    <p v-if="errorMessage" class="field-error">{{ errorMessage }}</p>
  </div>
</template>

<style scoped>
.field-upload-progress {
  margin-top: 0.5rem;
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.field-upload-preview {
  margin-top: 0.75rem;
  max-width: 240px;
  max-height: 160px;
  object-fit: cover;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.field-error {
  margin-top: 0.5rem;
  color: #b91c1c;
  font-size: 0.875rem;
}
</style>
