<!--

SPDX-FileCopyrightText: 2026 Felix Salfner
SPDX-License-Identifier: AGPL-3.0-or-later

This component renders a list of excluded folders as chips.
Each chip can be removed by clicking on the chip close "x".

If a folder path is too long, it is shortened with an ellipsis, and the full path
is shown using a popover element.

```vue
<template>
    <ExcludeFoldersFilter 
        :modelValue="excludedFolders" 
        @update:model-value="onRemoveFolder" />
</template>
```
-->

<template>
    <div class="exclude-folders-container">
        <template v-if="modelValue.length === 0">
            <div class="empty-filters">
                {{ t('filefinder', 'no folders are excluded. Click on action in search result to exclude folders.') }}
            </div>
        </template>
        <template v-else>
            <div class="non-empty-filters">
                <template v-for="(item, index) in modelValue">
                    <template v-if="needsShortening(item)">
                        <NcPopover :triggers="['hover']">
                            <template #trigger>
                                <NcChip 
                                    :text="shortenPath(item)" 
                                    :icon-path="mdiFolderOutline" 
                                    variant="error" 
                                    @close="onDelete(index)"/>
                            </template>
                            <template #default>
                                <div class="exclude-folders-popover">{{ item }}</div>
                            </template>
                        </NcPopover>
                    </template>
                    <template v-else>
                        <NcChip 
                            :text="shortenPath(item)" 
                            :icon-path="mdiFolderOutline" 
                            variant="error" 
                            @close="onDelete(index)"/>
                    </template>
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
    name: 'ExcludeFoldersFilter',
    components: {
        NcChip,
        NcPopover,
    },
    props: {
        /**
         * The list of Strings with the excluded folders 
         */
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
        /**
         * Called when the user clicked on the close element of the chip
         * @param idx the index in the modelValue list
         */
        onDelete(idx) {
            this.$emit('update:model-value', this.modelValue.toSpliced(idx,1));
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
.exclude-folders-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 20px;
    padding-right: 8px;
}

.exclude-folders-popover {
    padding: 5px 15px;
    font-size: small;
}

.empty-filters {
    font-style: italic;
}

.non-empty-filters {
    margin-top: 8px;
}


</style>
