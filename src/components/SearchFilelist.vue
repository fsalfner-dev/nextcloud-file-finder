<template>
    <table v-if="searchresult.files.length > 0" class="nc-table">
        <thead>
            <tr>
                <th class="sortable-header">
                    <span class="header-content">
                        File
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
                        Modified
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
                        Content
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
                        Actions
                    </span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(file, index) in searchresult.files">
                <td>
                    <span class="file-link">
                        <img :src="file.icon_link" class="file-icon" />
                        <a :href="file.link" target="_blank">{{ file.name }}</a>
                    </span>
                </td>
                <td>{{ file.modified }}</td>
                <td v-if="show_content"><ul><li v-for="highlight in file.highlights.content"><span class="highlight" v-html="highlight"></span></li></ul></td>
                <td><span class="header-content">
                        <NcPopover
                            :shown="showPopover[index]"
                            @update:shown="updateShow(index, $event)"
                            popupRole="menu">
                            <template #trigger>
                                <NcButton 
                                    aria-label="Exclude paths"
                                    size="small"
                                    variant="tertiary"
                                    :disabled="!isRootDir(file.name)">
                                    <template #icon>
                                        <IconFolderCancelOutline :size="15" />
                                    </template>
                                </NcButton>
                            </template>
                            <template #default>
                                <div class="exclude-folder-popover">
                                    <div class="exclude-folder-popover-heading">
                                        Exclude all files and folders under ...
                                    </div>
                                    <ul>
                                        <NcListItem v-for="folder in extractFolders(file.name)"
                                            compact
                                            :name="folder"
                                            @click="onItemClick(index,folder)">
                                            <template #icon>
                                                <IconFolderCancelOutline :size="15" />
                                            </template>
                                        </NcListItem>
                                    </ul>
                                </div>
                            </template>
                        </NcPopover>
                        <a :href="file.link" target="_blank">
                            <NcButton 
                                aria-label="Open file"
                                size="small"
                                variant="tertiary">
                                <template #icon>
                                    <IconOpenInNew :size="15" />
                                </template>
                            </NcButton>
                        </a>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
    <div v-else class="noresult">No files to be displayed</div>
</template>

<script>
import { mdiFilePdfBox } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconFolderCancelOutline from 'vue-material-design-icons/FolderCancelOutline.vue'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'

export default {
    name: 'SearchFilelist',
    props: {
        searchresult: {},
        show_content: {
            type: Boolean,
            default: false
        },
        currentSort: {
            type: String,
            default: 'score'
        },
        currentSortOrder: {
            type: String,
            default: 'desc'
        }
    },
    emits: ['update:sort', 'update:sortOrder', 'excludeFolder'],
    setup() {
        return {
            mdiFilePdfBox,
        }
    },
    data() {
        return {
			showPopover: new Array(this.searchresult.files.length).fill(false),
        }
    },
	components: {
        NcIconSvgWrapper,
        ChevronUp,
        ChevronDown,
        NcButton,
        NcPopover,
        NcListItem,
        IconFolderCancelOutline,
        IconOpenInNew,
    },
	methods: {
        handleSort(sortCriterion, sortOrder) {
            // Set the sort criterion and order
            if (this.currentSort !== sortCriterion) {
                this.$emit('update:sort', sortCriterion);
            }
            if (this.currentSortOrder !== sortOrder) {
                this.$emit('update:sortOrder', sortOrder);
            }
        },
        isRootDir(path) {
            return path.includes('/');
        },
        extractFolders(filePath) {
            // filePath has the form "Root/Dir1/Dir2/filename.ext"
            // @returns ["Root/", "Root/Dir1/", "Root/Dir1/Dir2/"]
            const segments = filePath.split('/');

            // remove the filename part
            segments.pop(); 

            const output = segments.map((_, index) => 
                segments.slice(0, index + 1).join('/') + '/');
            return output;
        },
        onItemClick(index, path) {
            this.$set(this.showPopover, index, false);
            this.$emit('excludeFolder', path);
        },
        updateShow(index, event) {
            this.$set(this.showPopover, index, event);
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

.exclude-folder-popover {
    width: 300px;
    padding: 2px 15px 2px 5px;
}

.exclude-folder-popover-heading {
    font-weight: bold;
    padding: 5px 15px 0px 5px;
}

</style>