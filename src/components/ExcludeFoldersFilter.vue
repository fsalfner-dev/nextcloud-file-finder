<template>
    <div class="exclude-folders-container">
        <template v-if="modelValue.length === 0">
            <div class="empty-filters">
                no folders are excluded. Click on action in search result to exclude folders.
            </div>
        </template>
        <template v-else>
            <template v-for="(item, index) in modelValue">
                <template v-if="needsShortening(item)">
                    <NcPopover :triggers="['hover']">
                        <template #trigger>
                            <NcChip :text="shortenPath(item)" :icon-path="mdiFolderOutline" variant="error" @close="onDelete(index)"/>
                        </template>
                        <template #default>
                            <div class="exclude-folders-popover">{{ item }}</div>
                        </template>
                    </NcPopover>
                </template>
                <template v-else>
                    <NcChip :text="shortenPath(item)" :icon-path="mdiFolderOutline" variant="error" @close="onDelete(index)"/>
                </template>
            </template>
        </template>
    </div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import { mdiFolderOutline} from '@mdi/js';

const CUTOFF_LENGTH = 25;

export default {
    name: 'ExcludeFoldersFilter',
    components: {
        NcChip,
        NcPopover,
    },
    props: {
        modelValue: {
            type: Array,
            default: [],
        },
    },
	setup() {
		return {
			mdiFolderOutline,
		}
	},
    emits: ['update:model-value'],
    methods: {
        onDelete(idx) {
            this.$emit('update:model-value', this.modelValue.toSpliced(idx,1));
        },
        needsShortening(path) {
            return path.length > CUTOFF_LENGTH;
        },
        shortenPath(path) {
            return path.substring(0,CUTOFF_LENGTH) + '...';
        }
    }
};

</script>

<style scoped lang="scss">
.exclude-folders-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 16px;
}

.exclude-folders-popover {
    padding: 5px 15px;
    font-size: small;
}

.empty-filters {
    font-style: italic;
}

</style>
