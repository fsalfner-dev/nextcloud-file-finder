<!--

SPDX-FileCopyrightText: 2026 Felix Salfner
SPDX-License-Identifier: AGPL-3.0-or-later

This component renders the search results as a table.

The table headers show up and down chevrons to modify the sort column and order. The current
sorting is highlighted by a bolder chevron.

Depending on whether full-text search is used or not, the column showing the fulltext highlights
is shown or hidden. If the column is shown, the search results contains a list of highlights,
which are rendered as an unordered list.

In the table each file is shown as a separate row. Each offers actions to the user:
  * exclude a folder that the file is in
  * drill down into the folder that the file is in
  * open the file in a separate tab

```vue
<template>
    <SearchFilelist 
        :files="searchresult_files" 
        :show_content="show_content_column"
        :currentSort="search_sort"
        :currentSortOrder="search_sort_order"
        @update:sort="onSortUpdate"
        @update:sortOrder="onSortOrderUpdate"
        @excludeFolder="addExcludedFolder"
        @folderDrilldown="setStartFolder"
    />
</template>
```
-->
<template>
    <table v-if="files.length > 0" class="nc-table">
        <thead>
            <tr>
                <th class="sortable-header">
                    <span class="header-content">
                        {{ t('filefinder','File') }}
                        <span class="sort-icons">
                            <ChevronUp 
                                :size="18" 
                                :class="currentSort === 'path' && currentSortOrder === 'asc' ? 'active' : 'inactive'"
                                @click.stop="handleSort('path', 'asc')"
                            />
                            <ChevronDown 
                                :size="18" 
                                :class="currentSort === 'path' && currentSortOrder === 'desc' ? 'active' : 'inactive'"
                                @click.stop="handleSort('path', 'desc')"
                            />
                        </span>
                    </span>
                </th>
                <th class="sortable-header">
                    <span class="header-content">
                        {{ t('filefinder','Modified') }}
                        <span class="sort-icons">
                            <ChevronUp 
                                :size="18" 
                                :class="currentSort === 'modified' && currentSortOrder === 'asc' ? 'active' : 'inactive'"
                                @click.stop="handleSort('modified', 'asc')"
                            />
                            <ChevronDown 
                                :size="18" 
                                :class="currentSort === 'modified' && currentSortOrder === 'desc' ? 'active' : 'inactive'"
                                @click.stop="handleSort('modified', 'desc')"
                            />
                        </span>
                    </span>
                </th>
                <th v-if="show_content" class="sortable-header">
                    <span class="header-content">
                        {{ t('filefinder','Content') }}
                        <span class="sort-icons">
                            <ChevronDown 
                                :size="18" 
                                :class="currentSort === 'score' && currentSortOrder === 'desc' ? 'active' : 'inactive'"
                                @click.stop="handleSort('score', 'desc')"
                            />
                        </span>
                    </span>
                </th>
                <th class="sortable-header">
                    <span class="header-content">
                        {{ t('filefinder','Actions') }}
                    </span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="file in files">
                <td>
                    <span class="file-link">
                        <img :src="file.icon_link" class="file-icon" />
                        <a :href="file.link" target="_blank">{{ file.name }}</a>
                    </span>
                </td>
                <td>{{ file.modified }}</td>
                <td v-if="show_content">
                    <ul>
                        <!-- file.highlights.content is a list of highlights
                             each highlight comes from Elasticsearch and already
                             contains <em> HTML tags for highlighting -->
                        <li v-for="highlight in file.highlights.content">
                            <span class="highlight" v-html="highlight"></span>
                        </li>
                    </ul>
                </td>
                <td><span class="header-content">
                        <FolderAction 
                            :filePath="file.name" 
                            @folderAction="onExcludeFolder" 
                            :explanation="t('filefinder','Exclude all files and folders under ...')">
                            <template #icon>
                                <IconFolderCancelOutline :size="20" />
                            </template>
                        </FolderAction>
                        <FolderAction 
                            :filePath="file.name" 
                            @folderAction="onFolderDrilldown" 
                            :explanation="t('filefinder','Only show files and folders under ...')">
                            <template #icon>
                                <IconFolderSearchOutline :size="20" />
                            </template>
                        </FolderAction>
                        <a :href="file.link" target="_blank">
                            <NcButton 
                                :aria-label="t('filefinder','Open file')"
                                size="small"
                                variant="tertiary">
                                <template #icon>
                                    <IconOpenInNew :size="20" />
                                </template>
                            </NcButton>
                        </a>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
    <div v-else class="noresult">
        {{ t('filefinder','No files to be displayed') }}
    </div>
</template>

<script>
import { mdiFilePdfBox } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import IconFolderCancelOutline from 'vue-material-design-icons/FolderCancelOutline.vue'
import IconFolderSearchOutline from 'vue-material-design-icons/FolderSearchOutline.vue'
import FolderAction from './FolderAction.vue'

export default {
    name: 'SearchFilelist',
    props: {
        /**
         * the search results to be rendered.
         * each file entry needs to have the structure:
         *   - name: The full path
         *   - content_type: the file's content type
         *   - highlights: a list of content with highlighted search terms
         *   - icon_link: an URL to the file type icon
         *   - modified: a localized string representation of the modification timestamp
         *   - link: an URL to open the file
         */
        files: {
            type: Array,
            default: []
        },

        /**
         * show the content column
         */
        show_content: {
            type: Boolean,
            default: false
        },

        /**
         * name of the sorting column
         * @values path, modified, score, 
         */
        currentSort: {
            type: String,
            default: 'score'
        },

        /** 
         * the sort order
         * @values asc, desc 
         */
        currentSortOrder: {
            type: String,
            default: 'desc'
        }
    },
    emits: ['update:sort', 'update:sortOrder', 'excludeFolder', 'folderDrilldown'],
    setup() {
        return {
            mdiFilePdfBox,
        }
    },
	components: {
        NcIconSvgWrapper,
        ChevronUp,
        ChevronDown,
        NcButton,
        IconOpenInNew,
        IconFolderCancelOutline,
        IconFolderSearchOutline,
        FolderAction,
    },
	methods: {

        /**
         * handle the user's click on a sorting chevron
         * @param sortCriterion 
         * @param sortOrder 
         */
        handleSort(sortCriterion, sortOrder) {
            // Set the sort criterion and order
            if (this.currentSort !== sortCriterion) {
                this.$emit('update:sort', sortCriterion);
            }
            if (this.currentSortOrder !== sortOrder) {
                this.$emit('update:sortOrder', sortOrder);
            }
        },

        /**
         * handle the event when a user selected a folder to be excluded
         * @param path the path to be excluded
         */
        onExcludeFolder(path) {
            this.$emit('excludeFolder', path);
        },

        /**
         * handle the event when a user selected a foler to drill down into
         * @param path the path to use as root folder for the search
         */
        onFolderDrilldown(path) {
            this.$emit('folderDrilldown', path);
        }
	},
}
</script>

<style scoped>

.nc-table {
    table-layout: fixed;
}

.nc-table th {
    padding: 8px 12px;
    font-weight: bold;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
    position: relative;
}

.sortable-header:hover {
    background-color: var(--color-background-hover, rgba(0, 0, 0, 0.05));
}

.header-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sort-icons {
    display: flex;
    flex-direction: row;
    gap: 0px;
    margin-left: 4px;
}

.sort-icons .inactive {
    opacity: 0.3;
}

.sort-icons .inactive:hover {
    opacity: 0.6;
}

.sort-icons .active {
    opacity: 1;
    color: var(--color-primary-element, #0082c9);
}

.nc-table td {
  padding: 8px 12px;
  vertical-align: top;
}

.nc-table td a {
    text-decoration-line: underline;
}

.nc-table td ul {
    list-style-type: disc;
    padding-left: 20px;
}

.file-icon {
    width: 24px;
    height: 24px;
}

.file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.noresult {
    font-style: italic;
}

::v-deep(span.highlight) {
    font-size: small;
}

::v-deep(span.highlight em) {
    font-style: italic;
    font-weight: 700;
}

</style>