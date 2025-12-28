<script setup>
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
    members: Array,
    relationshipTypes: Object,
});

const emit = defineEmits(['select']);

const containerRef = ref(null);
const containerWidth = ref(800);
const containerHeight = ref(600);
const highlightedMemberId = ref(null);

// Get relationship type label
const getTypeLabel = (slug) => {
    const allTypes = [
        ...(props.relationshipTypes?.immediate || []),
        ...(props.relationshipTypes?.extended || []),
        ...(props.relationshipTypes?.non_family || []),
    ];
    const type = allTypes.find(t => t.slug === slug);
    return type?.label || slug;
};

// Build nodes with positions (circular layout)
const nodes = computed(() => {
    const count = props.members.length;
    const centerX = containerWidth.value / 2;
    const centerY = containerHeight.value / 2;
    const radius = Math.min(centerX, centerY) - 80;

    return props.members.map((member, index) => {
        const angle = (2 * Math.PI * index) / count - Math.PI / 2;
        return {
            ...member,
            x: centerX + radius * Math.cos(angle),
            y: centerY + radius * Math.sin(angle),
        };
    });
});

// Build edges from relationships (deduplicated)
const edges = computed(() => {
    const edgeSet = new Set();
    const result = [];

    props.members.forEach(member => {
        if (member.all_related_members) {
            member.all_related_members.forEach(related => {
                // Create a unique key for this edge (smaller id first)
                const key = member.id < related.id
                    ? `${member.id}-${related.id}`
                    : `${related.id}-${member.id}`;

                if (!edgeSet.has(key)) {
                    edgeSet.add(key);
                    const fromNode = nodes.value.find(n => n.id === member.id);
                    const toNode = nodes.value.find(n => n.id === related.id);

                    if (fromNode && toNode) {
                        // Determine edge style based on relationship type
                        const relType = related.pivot?.relationship_type || '';
                        const isTreeType = ['parent', 'child', 'spouse', 'sibling'].includes(relType);

                        result.push({
                            key,
                            from: fromNode,
                            to: toNode,
                            label: getTypeLabel(relType),
                            type: relType,
                            isExtended: !isTreeType,
                        });
                    }
                }
            });
        }
    });

    return result;
});

// Get color based on member's relationships
const getNodeColor = (member) => {
    if (member.spouses?.length > 0) return 'from-pink-500 to-rose-500';
    if (member.children?.length > 0) return 'from-amber-500 to-orange-500';
    if (member.parents?.length > 0) return 'from-blue-500 to-indigo-500';
    return 'from-purple-500 to-violet-500';
};

// Calculate edge midpoint for label
const getEdgeMidpoint = (edge) => {
    return {
        x: (edge.from.x + edge.to.x) / 2,
        y: (edge.from.y + edge.to.y) / 2,
    };
};

// Calculate edge angle for label rotation
const getEdgeAngle = (edge) => {
    const dx = edge.to.x - edge.from.x;
    const dy = edge.to.y - edge.from.y;
    let angle = Math.atan2(dy, dx) * (180 / Math.PI);
    // Keep text readable (not upside down)
    if (angle > 90 || angle < -90) {
        angle += 180;
    }
    return angle;
};

// Get connected member IDs for highlighting
const connectedMemberIds = computed(() => {
    if (!highlightedMemberId.value) return new Set();

    const ids = new Set([highlightedMemberId.value]);
    const member = props.members.find(m => m.id === highlightedMemberId.value);

    if (member?.all_related_members) {
        member.all_related_members.forEach(r => ids.add(r.id));
    }

    return ids;
});

// Check if a node should be highlighted
const isNodeHighlighted = (nodeId) => {
    if (!highlightedMemberId.value) return true; // No selection = all visible
    return connectedMemberIds.value.has(nodeId);
};

// Check if an edge should be highlighted
const isEdgeHighlighted = (edge) => {
    if (!highlightedMemberId.value) return true;
    return edge.from.id === highlightedMemberId.value || edge.to.id === highlightedMemberId.value;
};

// Handle node click
const handleNodeClick = (node) => {
    if (highlightedMemberId.value === node.id) {
        // Clicking same node again clears the highlight
        highlightedMemberId.value = null;
    } else {
        highlightedMemberId.value = node.id;
    }
    emit('select', node);
};

// Clear highlight when clicking background
const handleBackgroundClick = () => {
    highlightedMemberId.value = null;
};

onMounted(() => {
    if (containerRef.value) {
        const rect = containerRef.value.getBoundingClientRect();
        containerWidth.value = rect.width || 800;
        containerHeight.value = Math.max(500, rect.width * 0.6);
    }
});
</script>

<template>
    <div ref="containerRef" class="w-full overflow-auto">
        <svg
            :width="containerWidth"
            :height="containerHeight"
            class="mx-auto"
            @click.self="handleBackgroundClick"
        >
            <!-- Background rect for click handling -->
            <rect
                :width="containerWidth"
                :height="containerHeight"
                fill="transparent"
                @click="handleBackgroundClick"
            />

            <!-- Defs for glow effect -->
            <defs>
                <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                    <feMerge>
                        <feMergeNode in="coloredBlur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>
            </defs>

            <!-- Edges -->
            <g>
                <g
                    v-for="edge in edges"
                    :key="edge.key"
                    :class="[
                        'transition-opacity duration-300',
                        isEdgeHighlighted(edge) ? 'opacity-100' : 'opacity-20'
                    ]"
                >
                    <!-- Line -->
                    <line
                        :x1="edge.from.x"
                        :y1="edge.from.y"
                        :x2="edge.to.x"
                        :y2="edge.to.y"
                        :stroke="isEdgeHighlighted(edge) && highlightedMemberId ? '#f59e0b' : (edge.isExtended ? '#9333ea' : '#6b7280')"
                        :stroke-width="isEdgeHighlighted(edge) && highlightedMemberId ? 3 : (edge.isExtended ? 1 : 2)"
                        :stroke-dasharray="edge.isExtended ? '5,5' : 'none'"
                    />
                    <!-- Label background -->
                    <rect
                        :x="getEdgeMidpoint(edge).x - 30"
                        :y="getEdgeMidpoint(edge).y - 8"
                        width="60"
                        height="16"
                        rx="8"
                        :fill="isEdgeHighlighted(edge) && highlightedMemberId ? '#d97706' : (edge.isExtended ? '#581c87' : '#374151')"
                    />
                    <!-- Label text -->
                    <text
                        :x="getEdgeMidpoint(edge).x"
                        :y="getEdgeMidpoint(edge).y + 4"
                        text-anchor="middle"
                        class="text-[10px] fill-white font-medium"
                    >
                        {{ edge.label }}
                    </text>
                </g>
            </g>

            <!-- Nodes -->
            <g
                v-for="node in nodes"
                :key="node.id"
                :class="[
                    'transition-opacity duration-300',
                    isNodeHighlighted(node.id) ? 'opacity-100' : 'opacity-20'
                ]"
            >
                <!-- Highlight ring for selected node -->
                <circle
                    v-if="highlightedMemberId === node.id"
                    :cx="node.x"
                    :cy="node.y"
                    r="40"
                    fill="none"
                    stroke="#f59e0b"
                    stroke-width="3"
                    class="animate-pulse"
                    filter="url(#glow)"
                />
                <!-- Node circle -->
                <circle
                    :cx="node.x"
                    :cy="node.y"
                    r="32"
                    :class="[
                        'cursor-pointer transition-all duration-200 hover:opacity-80',
                        node.gender === 'female' ? 'fill-pink-500' : 'fill-amber-500'
                    ]"
                    :filter="highlightedMemberId === node.id ? 'url(#glow)' : 'none'"
                    @click.stop="handleNodeClick(node)"
                />
                <!-- Node initials -->
                <text
                    :x="node.x"
                    :y="node.y + 5"
                    text-anchor="middle"
                    class="text-sm font-bold fill-white pointer-events-none"
                >
                    {{ node.first_name?.[0] }}{{ node.last_name?.[0] || '' }}
                </text>
                <!-- Node name -->
                <text
                    :x="node.x"
                    :y="node.y + 52"
                    text-anchor="middle"
                    :class="[
                        'text-xs pointer-events-none transition-colors duration-300',
                        highlightedMemberId === node.id ? 'fill-amber-400 font-medium' : 'fill-gray-300'
                    ]"
                >
                    {{ node.display_name }}
                </text>
            </g>
        </svg>

        <!-- Legend -->
        <div class="flex justify-center gap-6 mt-4 text-sm text-gray-400">
            <div class="flex items-center gap-2">
                <div class="w-8 h-0.5 bg-gray-500"></div>
                <span>Immediate family</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-0.5 bg-purple-600 border-dashed" style="border-top: 2px dashed #9333ea;"></div>
                <span>Extended family</span>
            </div>
        </div>
    </div>
</template>
