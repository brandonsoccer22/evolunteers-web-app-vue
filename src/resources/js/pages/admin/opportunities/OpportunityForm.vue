<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardFooter from '@/components/ui/card/CardFooter.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { useAxios } from '@/composables/useAxios';
import AppLayout from '@/layouts/AppLayout.vue';
import opportunities from '@/routes/admin/opportunities';
import opportunityOrganizations from '@/routes/admin/opportunities/organizations';
import opportunityTags from '@/routes/admin/opportunities/tags';
import VueAsyncSelect from '@/components/ui/select/VueAsyncSelect.vue';
import type { BreadcrumbItem, Opportunity, Organization, Tag } from '@/types';

const props = defineProps<{
    data?: {
        opportunity?: Opportunity | null;
    } | null;
}>();

const axios = useAxios();
const page = usePage();
const initialOpportunity = computed<Opportunity | null>(() => props.data?.opportunity ?? null);
const currentOpportunityId = ref<number | null>(initialOpportunity.value?.id ?? null);
const isEdit = computed(() => currentOpportunityId.value !== null);

interface OpportunityFormState {
    name: string;
    description: string;
    url: string;
    organization_ids: number[];
    tag_names: string[];
    start_date: string | null;
    end_date: string | null;
    start_time: string | null;
    end_time: string | null;
}

const form = ref<OpportunityFormState>({
    name: '',
    description: '',
    url: '',
    organization_ids: [],
    tag_names: [],
    start_date: null,
    end_date: null,
    start_time: null,
    end_time: null,
});

const attachedOrganizations = ref<Organization[]>([]);
const tagInput = ref('');
const tags = ref<Tag[]>([]);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Opportunities', href: opportunities.index().url },
    { title: isEdit.value ? 'Edit opportunity' : 'Create opportunity', href: isEdit.value && currentOpportunityId.value ? opportunities.show(currentOpportunityId.value).url : opportunities.create().url },
]);

const { isFetching: isLoadingOpportunity, refetch: refetchOpportunity } = useQuery({
    queryKey: ['admin-opportunity', currentOpportunityId],
    enabled: computed(() => currentOpportunityId.value !== null),
    queryFn: async () => {
        if (currentOpportunityId.value === null) return null;
        const response = await axios.get(opportunities.show(currentOpportunityId.value).url);
        const opportunity = (response.data?.data ?? response.data) as Opportunity;
        hydrateForm(opportunity);
        return opportunity;
    },
});

const hydrateForm = (opportunity: Opportunity | null) => {
    if (!opportunity) return;
    form.value.name = opportunity.name ?? '';
    form.value.description = opportunity.description ?? '';
    form.value.url = opportunity.url ?? '';
    attachedOrganizations.value = opportunity.organizations ?? [];
    form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
    tags.value = opportunity.tags ?? [];
    form.value.tag_names = tags.value.map((tag) => tag.name);
    form.value.start_date = opportunity.start_date ?? null;
    form.value.end_date = opportunity.end_date ?? null;
    form.value.start_time = opportunity.start_time ?? null;
    form.value.end_time = opportunity.end_time ?? null;
};

watch(
    () => initialOpportunity.value,
    (opportunity) => {
        currentOpportunityId.value = opportunity?.id ?? null;
        if (opportunity) {
            hydrateForm(opportunity);
        }
    },
    { immediate: true },
);

const saving = ref(false);
const statusMessage = ref<string | null>(null);
const validationErrors = ref<Record<string, string[] | string>>({});
const sessionErrors = computed<Record<string, string[] | string>>(() => (page.props.errors ?? {}) as Record<string, string[] | string>);
const errorMessages = computed(() => {
    const combined = { ...sessionErrors.value, ...validationErrors.value };
    const messages: string[] = [];
    Object.values(combined).forEach((value) => {
        if (Array.isArray(value)) {
            messages.push(...value);
        } else if (typeof value === 'string') {
            messages.push(value);
        }
    });
    return messages;
});

const submit = async () => {
    saving.value = true;
    statusMessage.value = null;
    validationErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            description: form.value.description || null,
            url: form.value.url || null,
            organization_ids: attachedOrganizations.value.map((org) => org.id),
            tag_names: form.value.tag_names,
            start_date: form.value.start_date || null,
            end_date: form.value.end_date || null,
            start_time: form.value.start_time || null,
            end_time: form.value.end_time || null,
        };

        let response;
        if (currentOpportunityId.value) {
            response = await axios.patch(opportunities.update(currentOpportunityId.value).url, payload);
        } else {
            response = await axios.post(opportunities.store().url, payload);
        }

        const savedOpportunity = (response.data?.data ?? response.data) as Opportunity | null;
        if ((!currentOpportunityId.value || currentOpportunityId.value !== savedOpportunity?.id) && savedOpportunity?.id) {
            currentOpportunityId.value = savedOpportunity.id;
            window.history.replaceState({}, '', opportunities.show(savedOpportunity.id).url);
        }

        if (savedOpportunity) {
            hydrateForm(savedOpportunity);
        } else {
            await refetchOpportunity();
        }

        statusMessage.value = 'Opportunity saved';
    } catch (error) {
        const response = (error as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } })?.response;
        if (response?.status === 422) {
            validationErrors.value = response.data?.errors ?? {};
            statusMessage.value = response.data?.message ?? 'Please fix the highlighted errors.';
            return;
        }
        statusMessage.value = error instanceof Error ? error.message : 'Unable to save opportunity right now.';
    } finally {
        saving.value = false;
    }
};

const pageTitle = computed(() => (isEdit.value ? 'Edit Opportunity' : 'Create Opportunity'));

const organizationSearch = async (term: string) => {
    const response = await axios.get('/admin/organizations', {
        params: { search: term },
    });
    return (response.data?.data ?? response.data ?? []) as Organization[];
};

const selectedOrganization = ref<Organization | null>(null);
const addingOrg = ref(false);

const addOrganization = async () => {
    if (!selectedOrganization.value) return;
    const orgId = selectedOrganization.value.id;
    if (attachedOrganizations.value.some((org) => org.id === orgId)) {
        selectedOrganization.value = null;
        return;
    }

    if (!currentOpportunityId.value) {
        attachedOrganizations.value = [...attachedOrganizations.value, selectedOrganization.value];
        form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
        selectedOrganization.value = null;
        return;
    }

    addingOrg.value = true;
    try {
        await axios.post(opportunityOrganizations.attach({ opportunity: currentOpportunityId.value, organization: orgId }).url);
        await refetchOpportunity();
        selectedOrganization.value = null;
    } finally {
        addingOrg.value = false;
    }
};

const detachOrganization = async (orgId: number) => {
    if (!currentOpportunityId.value) {
        attachedOrganizations.value = attachedOrganizations.value.filter((org) => org.id !== orgId);
        form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
        return;
    }

    await axios.delete(opportunityOrganizations.detach({ opportunity: currentOpportunityId.value, organization: orgId }).url);
    await refetchOpportunity();
};

const addTag = () => {
    const value = tagInput.value.trim();
    if (!value) return;
    if (!tags.value.some((tag) => tag.name.toLowerCase() === value.toLowerCase())) {
        const newTag: Tag = { id: Date.now(), name: value };
        if (!currentOpportunityId.value) {
            tags.value = [...tags.value, newTag];
            form.value.tag_names = tags.value.map((tag) => tag.name);
            tagInput.value = '';
            return;
        }

        saveTag(newTag.name, 'add');
    }
    tagInput.value = '';
};

const removeTag = (name: string) => {
    if (!currentOpportunityId.value) {
        tags.value = tags.value.filter((tag) => tag.name !== name);
        form.value.tag_names = tags.value.map((tag) => tag.name);
        return;
    }
    saveTag(name, 'remove');
};

const saveTag = async (name: string, action: 'add' | 'remove') => {
    try {
        if (!currentOpportunityId.value) return;
        if (action === 'add') {
            const response = await axios.post(opportunityTags.add(currentOpportunityId.value).url, { tag_name: name });
            const payload = (response.data?.data ?? response.data) as Opportunity;
            tags.value = payload.tags ?? tags.value;
        } else {
            const response = await axios.delete(opportunityTags.remove(currentOpportunityId.value).url, {
                data: { tag_name: name },
            });
            const payload = (response.data?.data ?? response.data) as Opportunity;
            tags.value = payload.tags ?? tags.value.filter((tag) => tag.name !== name);
        }
        form.value.tag_names = tags.value.map((tag) => tag.name);
    } catch (error) {
        // Swallow errors to avoid interrupting the form flow; could surface a toast if desired
        console.error('Unable to update tag', error);
    } finally {
        tagInput.value = '';
    }
};
</script>

<template>
  <Head :title="pageTitle" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold">
            {{ pageTitle }}
          </h1>
          <p class="text-sm text-muted-foreground">
            {{ isEdit ? 'Update the opportunity details.' : 'Create a new opportunity.' }}
          </p>
        </div>
        <Button
          variant="secondary"
          :href="opportunities.index().url"
          as="a"
        >
          Back to opportunities
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Opportunity details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input
              id="name"
              v-model="form.name"
              name="name"
              autocomplete="off"
              required
            />
          </div>
          <div class="space-y-2">
            <Label for="description">Description</Label>
            <textarea
              id="description"
              v-model="form.description"
              name="description"
              rows="4"
              class="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
            />
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="start_date">Start date</Label>
              <Input
                id="start_date"
                v-model="form.start_date"
                type="date"
                name="start_date"
              />
            </div>
            <div class="space-y-2">
              <Label for="end_date">End date</Label>
              <Input
                id="end_date"
                v-model="form.end_date"
                type="date"
                name="end_date"
              />
            </div>
            <div class="space-y-2">
              <Label for="start_time">Start time</Label>
              <Input
                id="start_time"
                v-model="form.start_time"
                type="time"
                name="start_time"
              />
            </div>
            <div class="space-y-2">
              <Label for="end_time">End time</Label>
              <Input
                id="end_time"
                v-model="form.end_time"
                type="time"
                name="end_time"
              />
            </div>
          </div>
          <div class="space-y-2">
            <Label for="url">URL</Label>
            <Input
              id="url"
              v-model="form.url"
              name="url"
              type="url"
              autocomplete="off"
              placeholder="https://example.org"
            />
          </div>
        </CardContent>
        <CardFooter class="flex items-center gap-3">
          <Button
            type="button"
            :disabled="saving"
            @click="submit"
          >
            {{ saving ? 'Saving...' : 'Save opportunity' }}
          </Button>
          <span class="text-sm text-muted-foreground">
            <span v-if="isLoadingOpportunity">Loading opportunity...</span>
            <span v-else-if="statusMessage && statusMessage != errorMessages[0]">{{ statusMessage }}</span>
          </span>
        </CardFooter>
        <CardContent v-if="errorMessages.length" class="pt-0">
          <div class="space-y-1 text-sm text-destructive">
            <p v-for="(message, index) in errorMessages" :key="index">
              {{ message }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Organizations</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="org-search">Add organization</Label>
            <div class="flex gap-2">
              <VueAsyncSelect
                id="org-search"
                v-model="selectedOrganization"
                class="w-full"
                :fetch-options="organizationSearch"
                label-key="name"
                value-key="id"
                :min-query-length="0"
                :disabled-ids="attachedOrganizations.map((org) => org.id)"
                placeholder="Search organizations"
              />
              <Button
                type="button"
                :disabled="addingOrg || !selectedOrganization"
                @click="addOrganization"
              >
                {{ addingOrg ? 'Adding...' : 'Add' }}
              </Button>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ currentOpportunityId ? 'Attached organizations update immediately.' : 'Save the opportunity to persist attached organizations.' }}
            </p>
          </div>

          <div class="overflow-hidden rounded-lg border border-border">
            <table class="min-w-full text-sm">
              <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                  <th class="px-3 py-2">Name</th>
                  <th class="px-3 py-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-if="!attachedOrganizations.length"
                  class="text-muted-foreground"
                >
                  <td
                    class="px-3 py-3"
                    colspan="2"
                  >
                    No organizations attached.
                  </td>
                </tr>
                <tr
                  v-for="org in attachedOrganizations"
                  :key="org.id"
                  class="border-t border-border"
                >
                  <td class="px-3 py-2">
                    {{ org.name }}
                  </td>
                  <td class="px-3 py-2">
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      class="text-destructive hover:text-destructive"
                      @click="detachOrganization(org.id)"
                    >
                      Remove
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

<Card>
        <CardHeader>
          <CardTitle>Tags</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="flex gap-2">
            <Input
              v-model="tagInput"
              placeholder="Add a tag and press enter or add"
              @keyup.enter.prevent="addTag"
            />
            <Button
              type="button"
              variant="secondary"
              @click="addTag"
            >
              Add
            </Button>
          </div>
          <div
            v-if="!tags.length"
            class="text-sm text-muted-foreground"
          >
            No tags yet.
          </div>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="tag in tags"
              :key="tag.name"
              class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-sm"
            >
              {{ tag.name }}
              <button
                class="text-xs text-muted-foreground hover:text-destructive"
                type="button"
                @click="removeTag(tag.name)"
              >
                ✕
              </button>
            </span>
          </div>
        </CardContent>
      </Card>

    </div>
  </AppLayout>
</template>
