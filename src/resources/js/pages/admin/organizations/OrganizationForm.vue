<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardFooter from '@/components/ui/card/CardFooter.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import VueAsyncSelect from '@/components/ui/select/VueAsyncSelect.vue';
import { useAxios } from '@/composables/useAxios';
import AppLayout from '@/layouts/AppLayout.vue';
import organizations from '@/routes/admin/organizations';
import organizationUsers from '@/routes/admin/organizations/users';
import usersRoutes from '@/routes/admin/users';
import type { BreadcrumbItem, Organization, User } from '@/types';

type OrganizationWithUsers = Organization & { users?: User[] };
type SelectableUser = User & { label?: string };

const props = defineProps<{
    data?: {
        organization?: OrganizationWithUsers | null;
    } | null;
}>();

const axios = useAxios();
const initialOrganization = computed<OrganizationWithUsers | null>(() => props.data?.organization ?? null);
const currentOrganizationId = ref<number | null>(initialOrganization.value?.id ?? null);
const isEdit = computed(() => currentOrganizationId.value !== null);

interface OrganizationFormState {
    name: string;
    description: string;
    user_ids: number[];
}

const form = ref<OrganizationFormState>({
    name: '',
    description: '',
    user_ids: [],
});

const attachedUsers = ref<User[]>([]);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Organizations', href: organizations.index().url },
    {
        title: isEdit.value ? 'Edit organization' : 'Create organization',
        href: isEdit.value && currentOrganizationId.value ? organizations.show(currentOrganizationId.value).url : organizations.create().url,
    },
]);

const { isFetching: isLoadingOrganization, refetch: refetchOrganization } = useQuery({
    queryKey: ['admin-organization', currentOrganizationId],
    enabled: computed(() => currentOrganizationId.value !== null),
    queryFn: async () => {
        if (currentOrganizationId.value === null) return null;
        const response = await axios.get(organizations.show(currentOrganizationId.value).url);
        const organization = (response.data?.data ?? response.data) as OrganizationWithUsers;
        hydrateForm(organization);
        return organization;
    },
    initialData: () => initialOrganization.value,
});

const hydrateForm = (organization: OrganizationWithUsers | null) => {
    if (!organization) return;
    form.value.name = organization.name ?? '';
    form.value.description = organization.description ?? '';
    attachedUsers.value = organization.users ?? [];
    form.value.user_ids = attachedUsers.value.map((user) => user.id);
};

watch(
    () => initialOrganization.value,
    (organization) => {
        currentOrganizationId.value = organization?.id ?? null;
        if (organization) {
            hydrateForm(organization);
        }
    },
    { immediate: true },
);

const saving = ref(false);
const statusMessage = ref<string | null>(null);

const submit = async () => {
    saving.value = true;
    statusMessage.value = null;
    try {
        const payload = {
            name: form.value.name,
            description: form.value.description || null,
            user_ids: attachedUsers.value.map((user) => user.id),
        };

        let response;
        if (currentOrganizationId.value) {
            response = await axios.patch(organizations.update(currentOrganizationId.value).url, payload);
        } else {
            response = await axios.post(organizations.store().url, payload);
        }

        const savedOrganization = (response.data?.data ?? response.data) as OrganizationWithUsers | null;
        if ((!currentOrganizationId.value || currentOrganizationId.value !== savedOrganization?.id) && savedOrganization?.id) {
            currentOrganizationId.value = savedOrganization.id;
            window.history.replaceState({}, '', organizations.show(savedOrganization.id).url);
        }

        if (savedOrganization) {
            hydrateForm(savedOrganization);
        } else {
            await refetchOrganization();
        }

        statusMessage.value = 'Organization saved';
    } catch (error) {
        if (error instanceof Error) {
            statusMessage.value = error.message;
        } else {
            statusMessage.value = 'Unable to save organization right now.';
        }
    } finally {
        saving.value = false;
    }
};

const pageTitle = computed(() => (isEdit.value ? 'Edit Organization' : 'Create Organization'));

const userSearch = async (term: string) => {
    const response = await axios.get(usersRoutes.index().url, {
        params: { search: term },
    });
    const results = (response.data?.data ?? response.data ?? []) as User[];
    return results.map((user) => ({
        ...user,
        label: `${user.first_name} ${user.last_name} (${user.email})`,
    })) as SelectableUser[];
};

const selectedUser = ref<SelectableUser | null>(null);
const addingUser = ref(false);

const addUser = async () => {
    if (!selectedUser.value) return;
    const userId = selectedUser.value.id;
    if (attachedUsers.value.some((user) => user.id === userId)) {
        selectedUser.value = null;
        return;
    }

    if (!currentOrganizationId.value) {
        attachedUsers.value = [...attachedUsers.value, selectedUser.value];
        form.value.user_ids = attachedUsers.value.map((user) => user.id);
        selectedUser.value = null;
        return;
    }

    addingUser.value = true;
    try {
        await axios.post(organizationUsers.attach({ organization: currentOrganizationId.value, user: userId }).url);
        await refetchOrganization();
        selectedUser.value = null;
    } finally {
        addingUser.value = false;
    }
};

const detachUser = async (userId: number) => {
    if (!currentOrganizationId.value) {
        attachedUsers.value = attachedUsers.value.filter((user) => user.id !== userId);
        form.value.user_ids = attachedUsers.value.map((user) => user.id);
        return;
    }

    await axios.delete(organizationUsers.detach({ organization: currentOrganizationId.value, user: userId }).url);
    await refetchOrganization();
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
                        {{ isEdit ? 'Update the organization details.' : 'Create a new organization.' }}
                    </p>
                </div>
                <Button variant="secondary" :href="organizations.index().url" as="a"> Back to organizations </Button>
            </div>

            <Card>
                <form @submit.prevent="submit">
                    <CardHeader>
                        <CardTitle>Organization details</CardTitle>
                        <p class="text-sm text-muted-foreground">Name, description, and members.</p>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" required placeholder="Organization name" type="text" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="description">Description</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    class="min-h-[90px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background transition-[border,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    placeholder="Describe the organization"
                                />
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border border-border/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">Members</p>
                                    <p class="text-xs text-muted-foreground">Attach users to this organization.</p>
                                </div>
                                <div class="flex flex-1 flex-col gap-2 md:flex-row md:items-center">
                                    <VueAsyncSelect
                                        v-model="selectedUser"
                                        :fetch-options="userSearch"
                                        :disabled="addingUser || isLoadingOrganization"
                                        label-key="label"
                                        value-key="id"
                                        placeholder="Search users..."
                                        class="md:flex-1"
                                    />
                                    <Button type="button" :disabled="addingUser || isLoadingOrganization || !selectedUser" @click="addUser()">
                                        {{ addingUser ? 'Adding...' : 'Attach user' }}
                                    </Button>
                                </div>
                            </div>

                            <div class="divide-y divide-border rounded-md border border-border/70">
                                <div v-if="!attachedUsers.length" class="px-3 py-2 text-sm text-muted-foreground">No users attached yet.</div>
                                <template v-else>
                                    <div v-for="user in attachedUsers" :key="user.id" class="flex items-center justify-between gap-3 px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-foreground">{{ user.first_name }} {{ user.last_name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="detachUser(user.id)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="flex items-center justify-between gap-3">
                        <div class="text-sm text-muted-foreground">
                            <span v-if="isLoadingOrganization">Loading organization...</span>
                            <span v-else-if="statusMessage">{{ statusMessage }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <Button type="submit" class="mt-2" :disabled="saving">
                                {{ saving ? 'Saving...' : 'Save organization' }}
                            </Button>
                        </div>
                    </CardFooter>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
