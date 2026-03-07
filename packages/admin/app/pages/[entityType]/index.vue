<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { useSchema } from '~/composables/useSchema'

const route = useRoute()
const { t } = useLanguage()

const entityType = computed(() => route.params.entityType as string)
const { schema, loading, error, fetch: fetchSchema } = useSchema(entityType.value)
onMounted(() => fetchSchema())
const entityLabel = computed(() => schema.value?.title ?? entityType.value)
const config = useRuntimeConfig()
useHead({ title: computed(() => `${entityLabel.value} | ${config.public.appName}`) })
</script>

<template>
  <div>
    <template v-if="!loading && error">
      <div class="page-header">
        <h1>{{ t('error_not_found') }}</h1>
      </div>
      <p class="error">{{ error }}</p>
      <NuxtLink to="/" class="btn">← {{ t('dashboard') }}</NuxtLink>
    </template>

    <template v-else>
      <div class="page-header">
        <h1>{{ entityLabel }}</h1>
        <NuxtLink :to="`/${entityType}/create`" class="btn btn-primary">
          {{ t('create_new') }}
        </NuxtLink>
      </div>

      <SchemaList :entity-type="entityType" />
    </template>
  </div>
</template>
