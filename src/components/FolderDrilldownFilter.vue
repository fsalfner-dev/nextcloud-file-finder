<!--

SPDX-FileCopyrightText: 2026 Felix Salfner
SPDX-License-Identifier: AGPL-3.0-or-later

This component shows the folder where search starts as a chip and allowing to 
remove it by clicking on the chip's close "x".

If the folder path is too long, it is shortened with an ellipsis, and the full path
is shown using a popover element.

```vue
<template>
    <FolderDrilldownFilter 
        :modelValue="folder" 
        @update:model-value="removeFolder" />
</template>
```
-->
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
                        @close="onDelete"/>
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
        /**
         * the path of the folder
         */
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
        /**
         * handler method when user clicks on the chip's delete cross
         */
        onDelete() {
            this.$emit('update:model-value', null);
        },

        /**
         * Tests if a path is too long and needs to be shortened
         * @param path The full path of a folder
         */
        needsShortening(path) {
            return path.length > CUTOFF_LENGTH;
        },

        /**
         * Cuts of the remainder of a path and adds an ellipsis
         * @param path the full path of a folder
         */
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
