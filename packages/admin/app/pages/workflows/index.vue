<script setup lang="ts">
import { useWorkflowDefinitions } from '~/composables/useWorkflowDefinitions'
import { useLanguage } from '~/composables/useLanguage'

const { t } = useLanguage()
const { workflows, loading, error, fetchWorkflows } = useWorkflowDefinitions()

onMounted(() => fetchWorkflows())

const { appName } = useAdminConfig()
useHead({ title: computed(() => `${t('workflows_list_title')} | ${appName}`) })
</script>

<template>
  <div>
    <div class="page-header">
      <h1>{{ t('workflows_list_title') }}</h1>
    </div>

    <div v-if="loading" class="loading">{{ t('loading') }}</div>

    <div v-else-if="error" class="error">{{ error }}</div>

    <template v-else>
      <p v-if="workflows.length === 0" class="empty-state">
        {{ t('workflows_empty') }}
      </p>

      <table v-else class="entity-table" data-testid="workflows-table">
        <thead>
          <tr>
            <th>{{ t('workflow_id') }}</th>
            <th>{{ t('workflow_label') }}</th>
            <th>{{ t('workflow_state_count') }}</th>
            <th>{{ t('workflow_transition_count') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="workflow in workflows" :key="workflow.id">
            <td><code>{{ workflow.id }}</code></td>
            <td>{{ workflow.label }}</td>
            <td>{{ workflow.states.length }}</td>
            <td>{{ workflow.transitions.length }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>
