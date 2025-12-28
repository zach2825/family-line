<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TimelineEntryForm from '@/Components/Timeline/TimelineEntryForm.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    entry: Object,
    eventTypes: Object,
    visibilityOptions: Object,
    familyMembers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    title: props.entry.title,
    content: props.entry.content || '',
    event_date: props.entry.event_date?.split('T')[0] || '',
    event_end_date: props.entry.event_end_date?.split('T')[0] || '',
    event_type: props.entry.event_type,
    location: props.entry.location || '',
    people_involved: props.entry.people_involved || [],
    member_ids: props.entry.family_members?.map(m => m.id) || [],
    family_surname: props.entry.family_surname || '',
    visibility: props.entry.visibility,
    is_published: props.entry.is_published,
});

const submit = () => form.put(route('timeline.update', props.entry.id));
</script>

<template>
    <AppLayout title="Edit Timeline Entry">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('timeline.show', entry.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Timeline Entry
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <TimelineEntryForm
                        :form="form"
                        :event-types="eventTypes"
                        :visibility-options="visibilityOptions"
                        :family-members="familyMembers"
                        submit-label="Save Changes"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
