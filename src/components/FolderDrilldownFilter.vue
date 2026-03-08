<template>
    <div class="folders-drilldown-container">
        <template v-if="modelValue === null">
            <div class="empty-drilldown">
                {{ t('filefinder', 'Search starts at your root folder. Click on action in search result to limit search to a specific folder.') }}
            </div>
        </template>
        <template v-else>
            <div class="non-empty-drilldown">
                <template v-if="needsShortening(modelValue)">
                    <NcPopover :triggers="['hover']">
                        <template #trigger>
                            <NcChip 
                                :text="shortenPath(modelValue)" 
                                :icon-path="mdiFolderOutline" 
                                variant="warning" 
                                @close="onDelete(index)"/>
                        </template>
                        <template #default>
                            <div class="folder-drilldown-popover">{{ modelValue }}</div>
                        </template>
                    </NcPopover>
                </template>
                <template v-else>
                    <NcChip 
                        :text="shortenPath(modelValue)" 
                        :icon-path="mdiFolderOutline" 
                        variant="warning" 
                        @close="onDelete(index)"/>
                </template>
            </div>
        </template>
    </div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import { mdiFolderOutline} from '@mdi/js';

const CUTOFF_LENGTH = 25;

export default {
    name: 'FolderDrilldownFilter',
    components: {
        NcChip,
        NcPopover,
    },
    props: {
        modelValue: {
            type: String,
            default: null,
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
            this.$emit('update:model-value', null);
        },
        needsShortening(path) {
            return path.length > CUTOFF_LENGTH;
        },
        shortenPath(path) {
            return path.substring(0,CUTOFF_LENGTH) + '…';
        }
    }
};

</script>

<style scoped lang="scss">
.folders-drilldown-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 20px;
    padding-right: 8px;
}

.folder-drilldown-popover {
    padding: 5px 15px;
    font-size: small;
}

.empty-drilldown {
    font-style: italic;
}

.non-empty-drilldown {
    margin-top: 8px;
}

</style>
