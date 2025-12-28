<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    existingMembers: {
        type: Array,
        default: () => [],
    },
    relationshipTypes: {
        type: Object,
        default: () => ({ immediate: [], extended: [], non_family: [] }),
    },
    currentUserMember: {
        type: Object,
        default: null,
    },
});

// Filter out current user from existing members list
const otherMembers = computed(() => {
    if (!props.existingMembers) return [];
    if (!props.currentUserMember) return props.existingMembers;
    return props.existingMembers.filter(m => m.id !== props.currentUserMember.id);
});

const form = useForm({
    first_name: '',
    last_name: '',
    nickname: '',
    birth_date: '',
    death_date: '',
    gender: '',
    notes: '',
    is_living: true,
    photo: null,
    relationships: [],
    link_to_me: false, // Link this family member to my user account
});

const photoPreview = ref(null);

const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    }
};

const addRelationship = () => {
    form.relationships.push({
        member_id: '',
        type: '',
    });
};

const removeRelationship = (index) => {
    form.relationships.splice(index, 1);
};

const submit = () => {
    form.post(route('family-members.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <AppLayout title="Add Family Member">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('family-members.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Add Family Member
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
                                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-900 dark:file:text-amber-300"
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
                            <InputLabel for="nickname" value="Nickname (e.g., Mom, Grandpa Joe)" />
                            <TextInput
                                id="nickname"
                                v-model="form.nickname"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="How family members refer to this person"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Used for smart matching in timeline entries
                            </p>
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
                                <InputError :message="form.errors.gender" class="mt-2" />
                            </div>
                        </div>

                        <!-- Living Status & This is Me -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex items-center">
                                <input
                                    id="is_living"
                                    v-model="form.is_living"
                                    type="checkbox"
                                    class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-amber-600 shadow-sm focus:ring-amber-500"
                                />
                                <InputLabel for="is_living" value="Currently living" class="ml-2" />
                            </div>

                            <div v-if="!currentUserMember" class="flex items-center p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                <input
                                    id="link_to_me"
                                    v-model="form.link_to_me"
                                    type="checkbox"
                                    class="rounded dark:bg-gray-900 border-blue-300 dark:border-blue-700 text-blue-600 shadow-sm focus:ring-blue-500"
                                />
                                <label for="link_to_me" class="ml-2 text-sm text-blue-800 dark:text-blue-200">
                                    This is me (link to my account)
                                </label>
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
                                placeholder="Any additional notes about this person..."
                            />
                            <InputError :message="form.errors.notes" class="mt-2" />
                        </div>

                        <!-- Relationships -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Relationships</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">How is this person related to existing family members?</p>
                                </div>
                                <button
                                    type="button"
                                    @click="addRelationship"
                                    class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400"
                                >
                                    + Add Relationship
                                </button>
                            </div>

                            <div v-for="(rel, index) in form.relationships" :key="index" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <span class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">This person is the</span>
                                <select
                                    v-model="rel.type"
                                    class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm text-sm"
                                >
                                    <option value="">select...</option>
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
                                <span class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">of</span>
                                <select
                                    v-model="rel.member_id"
                                    class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm text-sm"
                                >
                                    <option value="">select person...</option>
                                    <option
                                        v-if="currentUserMember"
                                        :value="currentUserMember.id"
                                        class="font-medium"
                                    >
                                        Me ({{ currentUserMember.display_name || currentUserMember.first_name }})
                                    </option>
                                    <option v-if="currentUserMember && otherMembers.length > 0" disabled>──────────</option>
                                    <option
                                        v-for="member in otherMembers"
                                        :key="member.id"
                                        :value="member.id"
                                    >
                                        {{ member.nickname || `${member.first_name} ${member.last_name || ''}`.trim() }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    @click="removeRelationship(index)"
                                    class="text-red-500 hover:text-red-700 p-1"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="form.relationships.length === 0" class="text-center py-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                    No relationships added yet.
                                </p>
                                <button
                                    type="button"
                                    @click="addRelationship"
                                    class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400"
                                >
                                    + Add a relationship (e.g., "spouse of Me")
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <PrimaryButton
                                :disabled="form.processing"
                                class="bg-amber-600 hover:bg-amber-700"
                            >
                                Add Family Member
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
