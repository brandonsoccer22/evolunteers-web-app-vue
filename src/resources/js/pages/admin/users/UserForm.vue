<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query';
import { Head } from '@inertiajs/vue3';
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
import users from '@/routes/admin/users';
import userOrganizations from '@/routes/admin/users/organizations';
import type { BreadcrumbItem, Organization, User } from '@/types';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    data?: {
        user?: User | null;
        availableRoles?: string[];
    } | null;
}>();

const axios = useAxios();
const initialUser = computed<User | null>(() => props.data?.user ?? null);
const currentUserId = ref<number | null>(initialUser.value?.id ?? null);
const isEdit = computed(() => currentUserId.value !== null);
const availableRoles = computed<string[]>(() => props.data?.availableRoles ?? []);
const page = usePage();

interface UserFormState {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    organization_ids: number[];
    roles: string[];
}

const form = ref<UserFormState>({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    organization_ids: [],
    roles: ['User'],
});

const attachedOrganizations = ref<Organization[]>([]);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Users', href: users.index().url },
    { title: isEdit.value ? 'Edit user' : 'Create user', href: isEdit.value && currentUserId.value ? users.show(currentUserId.value).url : '/admin/users/create' },
]);

const { isFetching: isLoadingUser, refetch: refetchUser } = useQuery({
    queryKey: ['admin-user', currentUserId],
    enabled: computed(() => currentUserId.value !== null),
    queryFn: async () => {
        if (currentUserId.value === null) return null;
        const response = await axios.get(users.show(currentUserId.value).url);
        const user = (response.data?.data ?? response.data) as User;
        hydrateForm(user);
        return user;
    },
});

const hydrateForm = (user: User | null) => {
    if (!user) return;
    form.value.first_name = user.first_name ?? '';
    form.value.last_name = user.last_name ?? '';
    form.value.email = user.email ?? '';
    form.value.password = '';
    attachedOrganizations.value = user.organizations ?? [];
    if(attachedOrganizations.value){
        form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
    }
    form.value.roles = user.roles ?? [];

};

watch(
    () => initialUser.value,
    (user) => {
        currentUserId.value = user?.id ?? null;
        if (user) {
            hydrateForm(user);
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
        const payload: Partial<UserFormState> = {
            first_name: form.value.first_name,
            last_name: form.value.last_name,
            email: form.value.email,
            organization_ids: attachedOrganizations.value.map((org) => org.id),
            roles: form.value.roles,
        };

        if (form.value.password) {
            payload.password = form.value.password;
        }

        let response;
        if (currentUserId.value) {
            response = await axios.patch(users.update(currentUserId.value).url, payload);
        } else {
            response = await axios.post(users.store().url, payload);
        }

        const savedUser = (response.data?.data ?? response.data) as User | null;
        if ((!currentUserId.value || currentUserId.value !== savedUser?.id) && savedUser?.id) {
            currentUserId.value = savedUser.id;
            window.history.replaceState({}, '', users.show(savedUser.id).url);
        }

        if (savedUser) {
            hydrateForm(savedUser);
        } else {
            await refetchUser();
        }

        statusMessage.value = 'User saved';
    } catch (error) {
        if (error instanceof Error) {
            statusMessage.value = error.message;
        } else {
            statusMessage.value = 'Unable to save user right now.';
        }
    } finally {
        saving.value = false;
    }
};

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

    if (!currentUserId.value) {
        attachedOrganizations.value = [...attachedOrganizations.value, selectedOrganization.value];
        form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
        selectedOrganization.value = null;
        return;
    }

    addingOrg.value = true;
    try {
        await axios.post(userOrganizations.attach({ user: currentUserId.value, organization: orgId }).url);
        await refetchUser();
        selectedOrganization.value = null;
    } finally {
        addingOrg.value = false;
    }
};

const detachOrganization = async (orgId: number) => {
    if (!currentUserId.value) {
        attachedOrganizations.value = attachedOrganizations.value.filter((org) => org.id !== orgId);
        form.value.organization_ids = attachedOrganizations.value.map((org) => org.id);
        return;
    }

    await axios.delete(userOrganizations.detach({ user: currentUserId.value, organization: orgId }).url);
    await refetchUser();
};

const pageTitle = computed(() => (isEdit.value ? 'Edit User' : 'Create User'));
const canEditRoles = computed(() => {
    const roles = (page.props as any)?.auth?.roles ?? [];
    return Array.isArray(roles) && roles.includes('Admin');
});
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
            {{ isEdit ? 'Update user details and membership.' : 'Create a new user and set their memberships.' }}
          </p>
        </div>
        <Button
          variant="secondary"
          :href="users.index().url"
          as="a"
        >
          Back to users
        </Button>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>User details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="first_name">First name</Label>
                <Input
                  id="first_name"
                  v-model="form.first_name"
                  name="first_name"
                  autocomplete="given-name"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label for="last_name">Last name</Label>
                <Input
                  id="last_name"
                  v-model="form.last_name"
                  name="last_name"
                  autocomplete="family-name"
                  required
                />
              </div>
            </div>
            <div class="space-y-2">
              <Label for="email">Email</Label>
              <Input
                id="email"
                v-model="form.email"
                name="email"
                type="email"
                autocomplete="email"
                required
              />
            </div>
            <div class="space-y-2">
              <Label for="password">{{ isEdit ? 'Password (optional)' : 'Password' }}</Label>
              <Input
                id="password"
                v-model="form.password"
                name="password"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
              />
              <p class="text-xs text-muted-foreground">
                {{ isEdit ? 'Leave blank to keep the existing password.' : 'Minimum 8 characters.' }}
              </p>
            </div>
            <div
              v-if="canEditRoles"
              class="space-y-2"
            >
              <Label>Roles</Label>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="role in availableRoles"
                  :key="role"
                  class="inline-flex items-center gap-2 rounded border border-border px-3 py-2 text-sm"
                >
                  <input
                    v-model="form.roles"
                    :value="role"
                    type="checkbox"
                    class="h-4 w-4"
                  >
                  <span>{{ role }}</span>
                </label>
              </div>
              <p class="text-xs text-muted-foreground">Select one or more roles for the user.</p>
            </div>
          </CardContent>
          <CardFooter class="flex items-center gap-3">
            <Button
              type="button"
              :disabled="saving"
              @click="submit"
            >
              {{ saving ? 'Saving...' : 'Save user' }}
            </Button>
            <span class="text-sm text-muted-foreground">
              <span v-if="isLoadingUser">Loading user...</span>
              <span v-else-if="statusMessage">{{ statusMessage }}</span>
            </span>
          </CardFooter>
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
                {{ currentUserId ? 'Attached organizations update immediately.' : 'Save the user to persist attached organizations.' }}
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
      </div>
    </div>
  </AppLayout>
</template>
