<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    members: Array,
    relationships: Array,
    relationshipTypes: Object,
});

const form = useForm({
    member_id: '',
    relationship_type: '',
    related_member_id: '',
});

// Get the "Me" member if exists
const meember = computed(() => props.members.find(m => m.is_me));

// Members sorted with "Me" first
const sortedMembers = computed(() => {
    if (!meember.value) return props.members;
    return [
        meember.value,
        ...props.members.filter(m => !m.is_me)
    ];
});

// Available members for the second dropdown (exclude the first selected member)
const availableRelatedMembers = computed(() => {
    if (!form.member_id) return sortedMembers.value;
    return sortedMembers.value.filter(m => m.id !== parseInt(form.member_id));
});

// Get relationship type label
const getTypeLabel = (slug) => {
    const allTypes = [
        ...props.relationshipTypes.immediate || [],
        ...props.relationshipTypes.extended || [],
        ...props.relationshipTypes.non_family || [],
    ];
    const type = allTypes.find(t => t.slug === slug);
    return type?.label || slug;
};


const submit = () => {
    form.post(route('family-relationships.store-general'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const removeRelationship = (relationshipId) => {
    if (confirm('Remove this relationship?')) {
        router.delete(route('family-relationships.destroy', relationshipId), {
            preserveScroll: true,
        });
    }
};

const getMemberDisplay = (member) => {
    if (member.is_me) {
        return `Me (${member.display_name || member.first_name})`;
    }
    return member.nickname || `${member.first_name} ${member.last_name || ''}`.trim();
};
</script>

<template>
    <AppLayout title="Relationship Manager">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('family-members.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Relationship Manager
                    </h2>
                </div>
                <div class="flex space-x-2">
                    <Link :href="route('family.tree')" class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400">
                        View Family Tree
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Add Relationship Form -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Add Relationship
                    </h3>

                    <div v-if="members.length < 2" class="text-center py-8">
                        <p class="text-gray-500 dark:text-gray-400 mb-4">
                            You need at least 2 family members to create relationships.
                        </p>
                        <Link :href="route('family-members.create')" class="text-amber-600 hover:text-amber-700">
                            + Add Family Member
                        </Link>
                    </div>

                    <form v-else @submit.prevent="submit" class="space-y-4">
                        <div class="flex flex-col md:flex-row md:items-end gap-4">
                            <!-- First Person -->
                            <div class="flex-1">
                                <InputLabel value="Person" />
                                <select
                                    v-model="form.member_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                                >
                                    <option value="">Select person...</option>
                                    <option v-for="m in sortedMembers" :key="m.id" :value="m.id" :class="{ 'font-medium': m.is_me }">
                                        {{ getMemberDisplay(m) }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.member_id" class="mt-2" />
                            </div>

                            <!-- Relationship Type -->
                            <div class="flex-1">
                                <InputLabel value="is the" />
                                <select
                                    v-model="form.relationship_type"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                                >
                                    <option value="">Select relationship...</option>
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
                                <InputError :message="form.errors.relationship_type" class="mt-2" />
                            </div>

                            <!-- Second Person -->
                            <div class="flex-1">
                                <InputLabel value="of" />
                                <select
                                    v-model="form.related_member_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                                    :disabled="!form.member_id"
                                >
                                    <option value="">Select person...</option>
                                    <option v-for="m in availableRelatedMembers" :key="m.id" :value="m.id" :class="{ 'font-medium': m.is_me }">
                                        {{ getMemberDisplay(m) }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.related_member_id" class="mt-2" />
                            </div>

                            <PrimaryButton
                                type="submit"
                                :disabled="form.processing || !form.member_id || !form.relationship_type || !form.related_member_id"
                                class="bg-amber-600 hover:bg-amber-700 whitespace-nowrap"
                            >
                                Add
                            </PrimaryButton>
                        </div>

                        <!-- Preview sentence -->
                        <div v-if="form.member_id && form.relationship_type && form.related_member_id" class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                <strong>{{ members.find(m => m.id === parseInt(form.member_id))?.display_name }}</strong>
                                is the <strong>{{ getTypeLabel(form.relationship_type) }}</strong>
                                of <strong>{{ members.find(m => m.id === parseInt(form.related_member_id))?.display_name }}</strong>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Existing Relationships -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Current Relationships
                    </h3>

                    <div v-if="relationships.length === 0" class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400">
                            No relationships defined yet. Use the form above to connect family members.
                        </p>
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="rel in relationships"
                            :key="rel.id"
                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                        >
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">{{ rel.member_name }}</span>
                                <span class="text-gray-400">is</span>
                                <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 rounded text-xs">
                                    {{ getTypeLabel(rel.relationship_type) }}
                                </span>
                                <span class="text-gray-400">of</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ rel.related_member_name }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeRelationship(rel.id)"
                                class="text-red-500 hover:text-red-700 p-1"
                                title="Remove relationship"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
