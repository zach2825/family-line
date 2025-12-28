<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    member: Object,
    existingMembers: Array,
    relationshipTypes: Object,
    currentUserMember: {
        type: Object,
        default: null,
    },
});

const page = usePage();

// Check if this member is linked to the current user
const isLinkedToCurrentUser = computed(() => {
    return props.member.user_id === page.props.auth?.user?.id;
});

// Check if user already has another linked family member
const hasLinkedMember = computed(() => {
    return !!props.currentUserMember && props.currentUserMember.id !== props.member.id;
});

const form = useForm({
    first_name: props.member.first_name,
    last_name: props.member.last_name || '',
    nickname: props.member.nickname || '',
    birth_date: props.member.birth_date?.split('T')[0] || '',
    death_date: props.member.death_date?.split('T')[0] || '',
    gender: props.member.gender || '',
    notes: props.member.notes || '',
    is_living: props.member.is_living,
    photo: null,
});

const relationshipForm = useForm({
    related_member_id: '',
    relationship_type: '',
});

const photoPreview = ref(props.member.photo_path ? `/storage/${props.member.photo_path}` : null);
const showDeleteConfirm = ref(false);

const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(route('family-members.update', props.member.id), {
        forceFormData: true,
    });
};

const addRelationship = () => {
    relationshipForm.post(route('family-relationships.store', props.member.id), {
        preserveScroll: true,
        onSuccess: () => {
            relationshipForm.reset();
        },
    });
};

const removeRelationship = (relationshipId) => {
    router.delete(route('family-relationships.destroy', relationshipId), {
        preserveScroll: true,
    });
};

const deleteMember = () => {
    router.delete(route('family-members.destroy', props.member.id));
};

// Link this family member to current user's account
const linkToMyAccount = () => {
    router.post(route('family-members.link', props.member.id), {}, {
        preserveScroll: true,
    });
};

// Unlink this family member from current user's account
const unlinkFromMyAccount = () => {
    router.post(route('family-members.unlink', props.member.id), {}, {
        preserveScroll: true,
    });
};

// Get relationship type label from the relationshipTypes prop
const getTypeLabel = (slug) => {
    const allTypes = [
        ...(props.relationshipTypes.immediate || []),
        ...(props.relationshipTypes.extended || []),
        ...(props.relationshipTypes.non_family || []),
    ];
    const type = allTypes.find(t => t.slug === slug);
    return type?.label || slug;
};

// Get all current relationships for display
const getAllRelationships = () => {
    if (!props.member.all_related_members) return [];
    return props.member.all_related_members.map(m => ({
        member: m,
        type: m.pivot.relationship_type,
        label: getTypeLabel(m.pivot.relationship_type),
        pivotId: m.pivot.id,
    }));
};
</script>

<template>
    <AppLayout :title="`Edit ${member.display_name}`">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('family-members.show', member.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit {{ member.display_name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Photo Upload -->
                        <div class="flex items-center space-x-6">
                            <div class="shrink-0">
                                <div class="w-24 h-24 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center overflow-hidden">
                                    <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
                                    <svg v-else class="w-12 h-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Photo</span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="handlePhotoChange"
                                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                                    />
                                </label>
                                <InputError :message="form.errors.photo" class="mt-2" />
                            </div>
                        </div>

                        <!-- Name Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="first_name" value="First Name *" />
                                <TextInput
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.first_name" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="last_name" value="Last Name" />
                                <TextInput
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.last_name" class="mt-2" />
                            </div>
                        </div>

                        <!-- Nickname -->
                        <div>
                            <InputLabel for="nickname" value="Nickname" />
                            <TextInput
                                id="nickname"
                                v-model="form.nickname"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.nickname" class="mt-2" />
                        </div>

                        <!-- Dates & Gender -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel for="birth_date" value="Birth Date" />
                                <TextInput
                                    id="birth_date"
                                    v-model="form.birth_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.birth_date" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="death_date" value="Death Date" />
                                <TextInput
                                    id="death_date"
                                    v-model="form.death_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :disabled="form.is_living"
                                />
                                <InputError :message="form.errors.death_date" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="gender" value="Gender" />
                                <select
                                    id="gender"
                                    v-model="form.gender"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                                >
                                    <option value="">Not specified</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Living Status -->
                        <div class="flex items-center">
                            <input
                                id="is_living"
                                v-model="form.is_living"
                                type="checkbox"
                                class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-amber-600 shadow-sm focus:ring-amber-500"
                            />
                            <InputLabel for="is_living" value="Currently living" class="ml-2" />
                        </div>

                        <!-- Account Link Status -->
                        <div class="p-4 rounded-lg border-2" :class="isLinkedToCurrentUser ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">Account Link</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <template v-if="isLinkedToCurrentUser">
                                            <span class="text-blue-600 dark:text-blue-400 font-medium">This is you!</span>
                                            This family member is linked to your account.
                                        </template>
                                        <template v-else-if="hasLinkedMember">
                                            You're already linked to {{ currentUserMember.display_name || currentUserMember.first_name }}.
                                        </template>
                                        <template v-else>
                                            Link this family member to your account to mark it as "you" in the family tree.
                                        </template>
                                    </p>
                                </div>
                                <div>
                                    <SecondaryButton
                                        v-if="isLinkedToCurrentUser"
                                        @click="unlinkFromMyAccount"
                                        class="text-sm"
                                    >
                                        Unlink
                                    </SecondaryButton>
                                    <PrimaryButton
                                        v-else-if="!hasLinkedMember"
                                        @click="linkToMyAccount"
                                        class="bg-blue-600 hover:bg-blue-700 text-sm"
                                    >
                                        This is me
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <InputLabel for="notes" value="Notes" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                            />
                        </div>

                        <div class="flex justify-end pt-4">
                            <PrimaryButton :disabled="form.processing" class="bg-amber-600 hover:bg-amber-700">
                                Save Changes
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Relationships Section -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Relationships</h3>

                    <!-- Current Relationships -->
                    <div v-if="getAllRelationships().length" class="mb-6 space-y-2">
                        <div
                            v-for="rel in getAllRelationships()"
                            :key="`${rel.type}-${rel.member.id}`"
                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                        >
                            <div class="flex items-center">
                                <span class="px-2 py-1 text-xs rounded bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 mr-3">
                                    {{ rel.label }}
                                </span>
                                <span class="text-gray-900 dark:text-white">{{ rel.member.display_name }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeRelationship(rel.pivotId)"
                                class="text-red-500 hover:text-red-700"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Add Relationship Form -->
                    <div v-if="existingMembers.length" class="flex items-end space-x-3">
                        <div class="flex-1">
                            <InputLabel value="Add Relationship" />
                            <div class="flex space-x-2 mt-1">
                                <select
                                    v-model="relationshipForm.relationship_type"
                                    class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm text-sm"
                                >
                                    <option value="">Type...</option>
                                    <optgroup v-if="relationshipTypes.immediate?.length" label="Immediate Family">
                                        <option v-for="type in relationshipTypes.immediate" :key="type.slug" :value="type.slug">
                                            {{ type.label }}
                                        </option>
                                    </optgroup>
                                    <optgroup v-if="relationshipTypes.extended?.length" label="Extended Family">
                                        <option v-for="type in relationshipTypes.extended" :key="type.slug" :value="type.slug">
                                            {{ type.label }}
                                        </option>
                                    </optgroup>
                                    <optgroup v-if="relationshipTypes.non_family?.length" label="Non-Family">
                                        <option v-for="type in relationshipTypes.non_family" :key="type.slug" :value="type.slug">
                                            {{ type.label }}
                                        </option>
                                    </optgroup>
                                </select>
                                <select
                                    v-model="relationshipForm.related_member_id"
                                    class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm text-sm"
                                >
                                    <option value="">Select member...</option>
                                    <option
                                        v-if="currentUserMember"
                                        :value="currentUserMember.id"
                                        class="font-medium"
                                    >
                                        Me ({{ currentUserMember.display_name || currentUserMember.first_name }})
                                    </option>
                                    <option v-if="currentUserMember && existingMembers.length > 0" disabled>──────────</option>
                                    <option v-for="m in existingMembers" :key="m.id" :value="m.id">
                                        {{ m.nickname || `${m.first_name} ${m.last_name || ''}`.trim() }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addRelationship"
                            :disabled="!relationshipForm.relationship_type || !relationshipForm.related_member_id"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-sm font-medium rounded-md"
                        >
                            Add
                        </button>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mt-6 border-2 border-red-200 dark:border-red-900">
                    <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-4">Danger Zone</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Deleting this family member will remove them from all timeline entries. This action cannot be undone.
                    </p>
                    <DangerButton v-if="!showDeleteConfirm" @click="showDeleteConfirm = true">
                        Delete Family Member
                    </DangerButton>
                    <div v-else class="flex items-center space-x-3">
                        <DangerButton @click="deleteMember">
                            Yes, Delete Permanently
                        </DangerButton>
                        <button
                            type="button"
                            @click="showDeleteConfirm = false"
                            class="text-gray-500 hover:text-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
