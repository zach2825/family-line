<script setup>
import { watch, ref, computed } from 'vue';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FamilyMemberSelector from '@/Components/Timeline/FamilyMemberSelector.vue';

const props = defineProps({
    form: Object,
    eventTypes: Object,
    visibilityOptions: Object,
    familyMembers: {
        type: Array,
        default: () => [],
    },
    submitLabel: {
        type: String,
        default: 'Save',
    },
});

const emit = defineEmits(['submit', 'backfill-selected']);

// Track if user has manually edited fields (don't override their changes)
const userEditedEventType = ref(false);
const userEditedDate = ref(false);
const userEditedPeople = ref(false);

// Backfill suggestions
const backfillSuggestions = ref([]);
const selectedBackfills = ref([]);
const checkingBackfill = ref(false);

// Auto-detection confirmation
const pendingDetections = ref(null);
const showConfirmation = ref(false);

// Handler for updating member_ids
const updateMemberIds = (val) => {
    if (props.form) {
        props.form.member_ids = [...val];
    }
    userEditedPeople.value = true;
};

// Common family member keywords to detect
const familyKeywords = {
    'mom': 'Mom',
    'mother': 'Mom',
    'mama': 'Mom',
    'mum': 'Mom',
    'dad': 'Dad',
    'father': 'Dad',
    'papa': 'Dad',
    'grandma': 'Grandma',
    'grandmother': 'Grandma',
    'granny': 'Grandma',
    'nana': 'Grandma',
    'grandpa': 'Grandpa',
    'grandfather': 'Grandpa',
    'gramps': 'Grandpa',
    'brother': 'Brother',
    'bro': 'Brother',
    'sister': 'Sister',
    'sis': 'Sister',
    'aunt': 'Aunt',
    'auntie': 'Aunt',
    'uncle': 'Uncle',
    'cousin': 'Cousin',
    'nephew': 'Nephew',
    'niece': 'Niece',
    'son': 'Son',
    'daughter': 'Daughter',
    'husband': 'Husband',
    'wife': 'Wife',
    'spouse': 'Spouse',
    'baby': 'Baby',
};

// Event type keywords
const eventTypeKeywords = {
    birth: ['birth', 'born', 'baby', 'newborn', 'arrived', 'delivered'],
    death: ['death', 'died', 'passed', 'funeral', 'memorial', 'rip', 'rest in peace', 'passing'],
    marriage: ['marriage', 'married', 'wedding', 'engaged', 'engagement', 'wed'],
    milestone: ['milestone', 'graduation', 'graduated', 'first', 'achievement', 'promotion', 'retired', 'birthday', 'bday', 'anniversary', 'turning'],
    story: ['story', 'remember', 'memory', 'tale', 'told'],
    photo: ['photo', 'picture', 'photograph', 'snapshot'],
};

// Detect event type from title
const detectEventType = (title) => {
    const lowerTitle = title.toLowerCase();
    for (const [type, keywords] of Object.entries(eventTypeKeywords)) {
        for (const keyword of keywords) {
            if (lowerTitle.includes(keyword)) {
                return type;
            }
        }
    }
    return null;
};

// Extract people from title - returns string names (only family keywords, not random capitalized words)
const extractPeople = (title) => {
    const lowerTitle = title.toLowerCase();
    const foundPeople = new Set();

    // Only check for family keywords - don't try to detect capitalized words
    // as that causes false positives like "Picked", "Flowers", etc.
    for (const [keyword, name] of Object.entries(familyKeywords)) {
        // Match whole words with possessive forms
        const regex = new RegExp(`\\b${keyword}(?:'s|s)?\\b`, 'i');
        if (regex.test(lowerTitle)) {
            foundPeople.add(name);
        }
    }

    return Array.from(foundPeople);
};

// Match text against actual family members from database - returns member IDs
const matchFamilyMembers = (title) => {
    const lowerTitle = title.toLowerCase();
    const matchedIds = new Set();
    const matchedNames = [];

    // Extract all potential name words (2+ chars)
    const rawWords = lowerTitle.split(/\s+/)
        .map(w => w.replace(/[^a-zA-Z']/g, '').toLowerCase())
        .filter(w => w.length >= 2);

    // Create variations: original word + word without possessive 's
    const words = [];
    for (const w of rawWords) {
        words.push(w.replace(/'/g, '')); // "mom's" -> "moms"
        if (w.endsWith("'s")) {
            words.push(w.slice(0, -2)); // "mom's" -> "mom"
        } else if (w.endsWith("s") && w.length > 3) {
            words.push(w.slice(0, -1)); // "moms" -> "mom" (but not "as" -> "a")
        }
    }

    for (const member of props.familyMembers) {
        const firstName = member.first_name?.toLowerCase();
        const lastName = member.last_name?.toLowerCase();
        const nickname = member.nickname?.toLowerCase();

        // Check if any word matches this member's name/nickname
        for (const word of words) {
            if (firstName && firstName === word) {
                matchedIds.add(member.id);
                matchedNames.push({ id: member.id, match: word, member });
                break;
            }
            if (nickname && nickname === word) {
                matchedIds.add(member.id);
                matchedNames.push({ id: member.id, match: word, member });
                break;
            }
            // Partial match for nicknames (barb -> Barbara)
            if (firstName && firstName.length > 3 && firstName.startsWith(word) && word.length >= 3) {
                matchedIds.add(member.id);
                matchedNames.push({ id: member.id, match: word, member });
                break;
            }
        }
    }

    return { ids: Array.from(matchedIds), matches: matchedNames };
};

// Parse age/years from title and calculate date
const parseAgeAndCalculateDate = (title) => {
    const lowerTitle = title.toLowerCase();

    // Patterns to match:
    // "50th birthday" -> 50 years ago
    // "25th anniversary" -> 25 years ago
    // "turning 30" -> 30 years ago (birth date)
    // "1st birthday" -> 1 year ago

    // Match ordinal numbers (1st, 2nd, 3rd, 50th, etc.)
    const ordinalMatch = lowerTitle.match(/(\d+)(?:st|nd|rd|th)\s*(?:birthday|bday|anniversary)/);
    if (ordinalMatch) {
        const years = parseInt(ordinalMatch[1], 10);
        if (years > 0 && years < 150) {
            return calculateDateYearsAgo(years);
        }
    }

    // Match "turning X"
    const turningMatch = lowerTitle.match(/turning\s*(\d+)/);
    if (turningMatch) {
        const years = parseInt(turningMatch[1], 10);
        if (years > 0 && years < 150) {
            return calculateDateYearsAgo(years);
        }
    }

    // Match just number + birthday (e.g., "50 birthday", "mom 50th")
    const numBirthdayMatch = lowerTitle.match(/(\d+)\s*(?:st|nd|rd|th)?\s*(?:birthday|bday)/);
    if (numBirthdayMatch) {
        const years = parseInt(numBirthdayMatch[1], 10);
        if (years > 0 && years < 150) {
            return calculateDateYearsAgo(years);
        }
    }

    return null;
};

// Calculate date X years ago from today
const calculateDateYearsAgo = (years) => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - years);
    return date.toISOString().split('T')[0];
};

// Get today's date
const getTodayDate = () => {
    return new Date().toISOString().split('T')[0];
};

// Track manual edits
const onEventTypeChange = () => {
    userEditedEventType.value = true;
};

const onDateChange = () => {
    userEditedDate.value = true;
};

const onPeopleChange = () => {
    userEditedPeople.value = true;
};

// Set default date to today if not set
if (props.form && !props.form.event_date) {
    props.form.event_date = getTodayDate();
}

// Check for backfill suggestions when relevant fields change
let backfillDebounce = null;
const checkBackfillSuggestions = async () => {
    if (!props.form || !props.form.title || props.form.people_involved?.length === 0) {
        backfillSuggestions.value = [];
        return;
    }

    checkingBackfill.value = true;

    try {
        const response = await axios.post(route('api.timeline.check-backfill'), {
            event_type: props.form.event_type,
            people_involved: props.form.people_involved,
            event_date: props.form.event_date,
            title: props.form.title,
        });

        if (response.data.success) {
            backfillSuggestions.value = response.data.suggestions;
            // Reset selections when suggestions change
            selectedBackfills.value = [];
        }
    } catch (err) {
        console.error('Backfill check error:', err);
    } finally {
        checkingBackfill.value = false;
    }
};

// Watch for changes that might trigger backfill suggestions
watch(
    () => [props.form?.title, props.form?.event_type, props.form?.event_date, props.form?.people_involved],
    () => {
        if (!props.form) return;
        clearTimeout(backfillDebounce);
        backfillDebounce = setTimeout(checkBackfillSuggestions, 800);
    },
    { deep: true }
);

// Toggle backfill selection
const toggleBackfill = (index) => {
    const idx = selectedBackfills.value.indexOf(index);
    if (idx > -1) {
        selectedBackfills.value.splice(idx, 1);
    } else {
        selectedBackfills.value.push(index);
    }
};

// Get selected backfill items for submission
const getSelectedBackfills = () => {
    return selectedBackfills.value.map(idx => backfillSuggestions.value[idx]);
};

// Expose selected backfills
defineExpose({ getSelectedBackfills });

// Confirmation dialog functions
const showDetectionConfirmation = (detections) => {
    pendingDetections.value = detections;
    showConfirmation.value = true;
};

const applyDetections = () => {
    if (pendingDetections.value) {
        if (pendingDetections.value.event_type && !userEditedEventType.value) {
            props.form.event_type = pendingDetections.value.event_type;
        }
        if (pendingDetections.value.event_date && !userEditedDate.value) {
            props.form.event_date = pendingDetections.value.event_date;
        }
        // Apply family keyword detections (like "Mom" -> "Mom")
        if (pendingDetections.value.people?.length > 0 && !userEditedPeople.value) {
            const existing = props.form.people_involved || [];
            props.form.people_involved = [...new Set([...existing, ...pendingDetections.value.people])];
        }
        // Apply matched family member IDs - user clicked Apply so always apply these
        if (pendingDetections.value.member_ids?.length > 0) {
            const existingIds = props.form.member_ids || [];
            const newIds = [...new Set([...existingIds, ...pendingDetections.value.member_ids])];
            updateMemberIds(newIds);
        }
    }
    pendingDetections.value = null;
    showConfirmation.value = false;
};

const skipDetections = () => {
    pendingDetections.value = null;
    showConfirmation.value = false;
};

// Enhanced title watcher with confirmation
let titleDebounce = null;
watch(() => props.form?.title, (newTitle, oldTitle) => {
    if (!props.form || !newTitle || newTitle.length < 5) return;

    clearTimeout(titleDebounce);
    titleDebounce = setTimeout(() => {
        const detections = {};
        let hasSignificantDetection = false;

        // Detect event type
        const detectedType = detectEventType(newTitle);
        if (detectedType && props.eventTypes[detectedType] && !userEditedEventType.value && props.form.event_type !== detectedType) {
            detections.event_type = detectedType;
            detections.event_type_label = props.eventTypes[detectedType];
            hasSignificantDetection = true;
        }

        // Match against actual family members from database first
        const familyMatches = matchFamilyMembers(newTitle);
        const existingMemberIds = props.form.member_ids || [];
        const newMemberIds = familyMatches.ids.filter(id => !existingMemberIds.includes(id));
        if (newMemberIds.length > 0 && !userEditedPeople.value) {
            detections.member_ids = newMemberIds;
            detections.matched_members = familyMatches.matches.filter(m => newMemberIds.includes(m.id));
            hasSignificantDetection = true;
        }

        // Only detect generic people keywords if we didn't find actual family members
        // This avoids showing redundant "People: Mom" when we already show "Family Members: Jessica Admin [Mom]"
        if (!detections.matched_members?.length) {
            const people = extractPeople(newTitle);
            const newPeople = people.filter(p => !(props.form.people_involved || []).includes(p));
            if (newPeople.length > 0 && !userEditedPeople.value) {
                detections.people = newPeople;
            }
        }

        // Detect date from age
        const calculatedDate = parseAgeAndCalculateDate(newTitle);
        if (calculatedDate && !userEditedDate.value && props.form.event_date !== calculatedDate) {
            detections.event_date = calculatedDate;
            hasSignificantDetection = true;
        }

        // Show confirmation for significant detections (date or type changes or matched members)
        if (hasSignificantDetection) {
            showDetectionConfirmation(detections);
        } else if (detections.people?.length > 0) {
            // Auto-apply people without confirmation
            const existing = props.form.people_involved || [];
            props.form.people_involved = [...new Set([...existing, ...detections.people])];
        }
    }, 500);
}, { immediate: false });
</script>

<template>
    <form v-if="form" @submit.prevent="$emit('submit')" class="space-y-6">
        <!-- Auto-Detection Confirmation Dialog -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 transform -translate-y-2"
            enter-to-class="opacity-100 transform translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 transform translate-y-0"
            leave-to-class="opacity-0 transform -translate-y-2"
        >
            <div v-if="showConfirmation && pendingDetections" class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg p-4 mb-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                            Smart Detection Found
                        </h3>
                        <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            <p class="mb-2">We detected the following from your title:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li v-if="pendingDetections.event_type">
                                    <strong>Event Type:</strong> {{ pendingDetections.event_type_label }}
                                </li>
                                <li v-if="pendingDetections.event_date">
                                    <strong>Birth Date:</strong> {{ pendingDetections.event_date }}
                                    <span class="text-xs">(calculated from age)</span>
                                </li>
                                <li v-if="pendingDetections.people?.length > 0">
                                    <strong>People:</strong> {{ pendingDetections.people.join(', ') }}
                                </li>
                                <li v-if="pendingDetections.matched_members?.length > 0">
                                    <strong>Family Members:</strong>
                                    <span v-for="(m, idx) in pendingDetections.matched_members" :key="m.id">
                                        {{ m.member.full_name || m.member.first_name }}
                                        <span v-if="m.member.nickname" class="text-xs">[{{ m.member.nickname }}]</span>
                                        <span v-if="idx < pendingDetections.matched_members.length - 1">, </span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <div class="mt-4 flex space-x-3">
                            <button
                                type="button"
                                @click="applyDetections"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-amber-800 bg-amber-200 hover:bg-amber-300 dark:bg-amber-800 dark:text-amber-100 dark:hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition"
                            >
                                Apply These
                            </button>
                            <button
                                type="button"
                                @click="skipDetections"
                                class="inline-flex items-center px-3 py-1.5 border border-amber-300 dark:border-amber-600 text-xs font-medium rounded-md text-amber-700 dark:text-amber-300 bg-transparent hover:bg-amber-100 dark:hover:bg-amber-900/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition"
                            >
                                Skip
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Title with smart parsing hint -->
        <div>
            <InputLabel for="title" value="Title *" />
            <TextInput
                id="title"
                v-model="form.title"
                type="text"
                class="mt-1 block w-full"
                required
                autofocus
                placeholder="e.g., Mom's 50th Birthday Party, John and Sarah's Wedding..."
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Smart fill: Include names, relationships (mom, grandpa), or ages (50th birthday) to auto-populate fields
            </p>
            <InputError :message="form.errors?.title" class="mt-2" />
        </div>

        <!-- Event Type & Date Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <InputLabel for="event_type" value="Event Type *" />
                <select
                    id="event_type"
                    v-model="form.event_type"
                    @change="onEventTypeChange"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600 rounded-md shadow-sm"
                >
                    <option v-for="(label, value) in eventTypes" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>
                <InputError :message="form.errors?.event_type" class="mt-2" />
            </div>

            <div>
                <InputLabel for="event_date" value="Event Date" />
                <TextInput
                    id="event_date"
                    v-model="form.event_date"
                    @change="onDateChange"
                    type="date"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors?.event_date" class="mt-2" />
            </div>

            <div>
                <InputLabel for="event_end_date" value="End Date (optional)" />
                <TextInput
                    id="event_end_date"
                    v-model="form.event_end_date"
                    type="date"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors?.event_end_date" class="mt-2" />
            </div>
        </div>

        <!-- Location -->
        <div>
            <InputLabel for="location" value="Location" />
            <TextInput
                id="location"
                v-model="form.location"
                type="text"
                class="mt-1 block w-full"
                placeholder="e.g., Chicago, Illinois"
            />
            <InputError :message="form.errors?.location" class="mt-2" />
        </div>

        <!-- Content -->
        <div>
            <InputLabel for="content" value="Story / Description" />
            <textarea
                id="content"
                v-model="form.content"
                rows="6"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600 rounded-md shadow-sm"
                placeholder="Write your story, memory, or description here..."
            />
            <InputError :message="form.errors?.content" class="mt-2" />
        </div>

        <!-- Family Members Involved -->
        <div>
            <InputLabel value="Family Members Involved" />
            <FamilyMemberSelector
                :model-value="form.member_ids || []"
                @update:model-value="updateMemberIds"
                :family-members="familyMembers"
                class="mt-1"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Search and select family members, or create new ones on the fly
            </p>
        </div>

        <!-- Family Surname & Visibility -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <InputLabel for="family_surname" value="Family Surname" />
                <TextInput
                    id="family_surname"
                    v-model="form.family_surname"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="e.g., Johnson"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Used for family-based permissions
                </p>
                <InputError :message="form.errors?.family_surname" class="mt-2" />
            </div>

            <div>
                <InputLabel for="visibility" value="Visibility" />
                <select
                    id="visibility"
                    v-model="form.visibility"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600 rounded-md shadow-sm"
                >
                    <option v-for="(label, value) in visibilityOptions" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>
                <InputError :message="form.errors?.visibility" class="mt-2" />
            </div>
        </div>

        <!-- Published -->
        <div class="flex items-center">
            <input
                id="is_published"
                v-model="form.is_published"
                type="checkbox"
                class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-amber-600 shadow-sm focus:ring-amber-500 dark:focus:ring-amber-600 dark:focus:ring-offset-gray-800"
            />
            <InputLabel for="is_published" value="Published" class="ml-2" />
        </div>

        <!-- Backfill Suggestions -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 transform scale-95"
            enter-to-class="opacity-100 transform scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 transform scale-100"
            leave-to-class="opacity-0 transform scale-95"
        >
            <div v-if="backfillSuggestions.length > 0" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                <div class="flex items-center mb-3">
                    <svg class="h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Also Create Related Records?
                    </h4>
                    <div v-if="checkingBackfill" class="ml-2">
                        <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">
                    We noticed you're recording a celebration. Would you like to also create the original event record?
                </p>
                <div class="space-y-2">
                    <label
                        v-for="(suggestion, index) in backfillSuggestions"
                        :key="index"
                        class="flex items-start p-3 rounded-md cursor-pointer transition"
                        :class="selectedBackfills.includes(index)
                            ? 'bg-blue-100 dark:bg-blue-800/40 border-2 border-blue-400 dark:border-blue-500'
                            : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600'"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedBackfills.includes(index)"
                            @change="toggleBackfill(index)"
                            class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500"
                        />
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ suggestion.message }}
                            </span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ suggestion.description }}
                            </p>
                        </div>
                        <span class="ml-auto px-2 py-0.5 text-xs rounded-full capitalize"
                            :class="{
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': suggestion.type === 'birth',
                                'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200': suggestion.type === 'marriage',
                            }"
                        >
                            {{ suggestion.type }}
                        </span>
                    </label>
                </div>
            </div>
        </Transition>

        <div class="flex justify-end">
            <PrimaryButton
                class="bg-amber-600 hover:bg-amber-700 focus:bg-amber-700 active:bg-amber-800 focus:ring-amber-500"
                :disabled="form.processing"
            >
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
