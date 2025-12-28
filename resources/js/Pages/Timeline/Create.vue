<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TimelineEntryForm from '@/Components/Timeline/TimelineEntryForm.vue';
import QuickEntry from '@/Components/Timeline/QuickEntry.vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const flashMessage = computed(() => page.props.flash?.message);

const props = defineProps({
    eventTypes: Object,
    visibilityOptions: Object,
    hasApiKey: {
        type: Boolean,
        default: false,
    },
    familyMembers: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('quick'); // 'form' or 'quick' - default to quick for new entries
const showAiHelp = ref(false);
const formRef = ref(null);

const form = useForm({
    title: '',
    content: '',
    event_date: '',
    event_end_date: '',
    event_type: 'story',
    location: '',
    people_involved: [],
    member_ids: [],
    family_surname: '',
    visibility: 'immediate_family',
    is_published: true,
});

// Reset form to initial state
const resetForm = () => {
    // Explicitly clear all fields
    form.title = '';
    form.content = '';
    form.event_date = new Date().toISOString().split('T')[0];
    form.event_end_date = '';
    form.event_type = 'story';
    form.location = '';
    form.people_involved = [];
    form.member_ids = [];
    form.family_surname = '';
    form.visibility = 'immediate_family';
    form.is_published = true;
    form.clearErrors();

    // Reset the form component's internal state
    formRef.value?.resetState?.();

    activeTab.value = 'quick';
};

const submit = async () => {
    // Get selected backfill entries from the form component
    const backfills = formRef.value?.getSelectedBackfills?.() || [];

    // Submit the main form with backfill entries
    form.transform((data) => ({
        ...data,
        backfill_entries: backfills.map(b => b.prefill),
    })).post(route('timeline.store'));
};

const submitAndAddAnother = async () => {
    // Get selected backfill entries from the form component
    const backfills = formRef.value?.getSelectedBackfills?.() || [];

    // Submit the main form with backfill entries and flag to create another
    form.transform((data) => ({
        ...data,
        backfill_entries: backfills.map(b => b.prefill),
        create_another: true,
    })).post(route('timeline.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};

// Match a person string against family members - returns member ID if found
const matchPersonToFamilyMember = (personName) => {
    // Clean up the name - remove possessives and trim
    let lowerName = personName.toLowerCase().trim();

    // Create name variations to check
    const variations = [lowerName];
    // Remove possessives: "Mom's" -> "Mom"
    if (lowerName.endsWith("'s")) {
        variations.push(lowerName.slice(0, -2));
    }
    // Remove trailing 's' without apostrophe: "karlas" -> "karla"
    if (lowerName.endsWith('s') && lowerName.length > 3) {
        variations.push(lowerName.slice(0, -1));
    }

    for (const member of props.familyMembers) {
        const firstName = member.first_name?.toLowerCase();
        const lastName = member.last_name?.toLowerCase();
        const nickname = member.nickname?.toLowerCase();
        const fullName = `${firstName} ${lastName || ''}`.trim();

        for (const name of variations) {
            // Exact matches on first name, nickname, or full name
            if (firstName === name || nickname === name || fullName === name) {
                return member.id;
            }

            // Partial first name match (3+ chars)
            if (firstName && name.length >= 3 && firstName.startsWith(name)) {
                return member.id;
            }
        }
    }

    return null;
};

const applyParsedData = (parsed) => {
    if (parsed.title) form.title = parsed.title;
    if (parsed.event_type) form.event_type = parsed.event_type;
    if (parsed.event_date) form.event_date = parsed.event_date;
    if (parsed.event_end_date) form.event_end_date = parsed.event_end_date;
    if (parsed.location) form.location = parsed.location;
    if (parsed.content) form.content = parsed.content;
    if (parsed.people_involved?.length) {
        form.people_involved = [...new Set([...form.people_involved, ...parsed.people_involved])];

        // Try to match detected people to family members
        const matchedIds = [];
        for (const person of parsed.people_involved) {
            const memberId = matchPersonToFamilyMember(person);
            if (memberId && !form.member_ids.includes(memberId)) {
                matchedIds.push(memberId);
            }
        }
        if (matchedIds.length > 0) {
            form.member_ids = [...new Set([...form.member_ids, ...matchedIds])];
        }
    }

    // Also handle if parsed data directly includes member_ids
    if (parsed.member_ids?.length) {
        form.member_ids = [...new Set([...form.member_ids, ...parsed.member_ids])];
    }

    // Switch to form view to show/edit results
    activeTab.value = 'form';
};
</script>

<template>
    <AppLayout title="Add Timeline Entry">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('timeline.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Add Timeline Entry
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Flash Message -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 transform -translate-y-2"
                    enter-to-class="opacity-100 transform translate-y-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 transform translate-y-0"
                    leave-to-class="opacity-0 transform -translate-y-2"
                >
                    <div v-if="flashMessage" class="mb-4 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <p class="text-sm text-green-700 dark:text-green-300">{{ flashMessage }}</p>
                        </div>
                    </div>
                </Transition>

                <!-- Tab Navigation -->
                <div class="mb-4 flex space-x-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                    <button
                        @click="activeTab = 'quick'"
                        :class="[
                            'flex-1 py-2 px-4 text-sm font-medium rounded-md transition',
                            activeTab === 'quick'
                                ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow'
                                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Quick Entry</span>
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'form'"
                        :class="[
                            'flex-1 py-2 px-4 text-sm font-medium rounded-md transition',
                            activeTab === 'form'
                                ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow'
                                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Full Form</span>
                        </span>
                    </button>
                </div>

                <!-- Quick Entry Tab -->
                <div v-show="activeTab === 'quick'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            Describe your event
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Just describe what happened naturally. AI will extract the details and fill in the form for you.
                        </p>
                    </div>

                    <QuickEntry
                        @parsed="applyParsedData"
                        @submit="applyParsedData"
                    />

                    <!-- AI Setup Help - only show when API key is not configured -->
                    <div v-if="!hasApiKey" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="showAiHelp = !showAiHelp"
                            class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        >
                            <svg :class="['w-4 h-4 mr-2 transition-transform', showAiHelp ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            Enable AI-powered parsing (optional)
                        </button>

                        <div v-show="showAiHelp" class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg text-sm">
                            <p class="text-gray-700 dark:text-gray-300 mb-3">
                                For enhanced AI parsing with Claude, add your Anthropic API key to your <code class="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded">.env</code> file:
                            </p>
                            <pre class="bg-gray-800 text-gray-100 p-3 rounded-md overflow-x-auto mb-3">ANTHROPIC_API_KEY=your-api-key-here</pre>
                            <p class="text-gray-600 dark:text-gray-400 mb-2">
                                To get an API key:
                            </p>
                            <ol class="list-decimal list-inside text-gray-600 dark:text-gray-400 space-y-1">
                                <li>Visit <a href="https://console.anthropic.com" target="_blank" class="text-amber-600 hover:underline">console.anthropic.com</a></li>
                                <li>Sign up or log in to your account</li>
                                <li>Navigate to <strong>API Keys</strong> in the dashboard</li>
                                <li>Click <strong>Create Key</strong> and copy your new key</li>
                            </ol>
                            <p class="mt-3 text-gray-500 dark:text-gray-500 text-xs">
                                Without an API key, basic local parsing will still work for common patterns.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Full Form Tab -->
                <div v-show="activeTab === 'form'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <TimelineEntryForm
                        ref="formRef"
                        :form="form"
                        :event-types="eventTypes"
                        :visibility-options="visibilityOptions"
                        :family-members="familyMembers"
                        submit-label="Create Entry"
                        @submit="submit"
                        @submit-and-another="submitAndAddAnother"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
