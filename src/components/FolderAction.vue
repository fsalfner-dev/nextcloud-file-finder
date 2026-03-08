<!--
This component is used to let users pick a sub-path of the provided 'filePath'.

For example, if '/Media/Music/mySong.mp3' is provided as filePath, the user
can select on of the following folders:
  * /Media/
  * /Media/Music/

The component remders an `<NcButton>` with an icon provided as a slot as a trigger.
When the trigger icon is pressed, a popup occurs:
  * the 'explanation' prop is shown at the top of the popup
  * a list of paths is shown, and the slot icon is shown in front of each row

If the user clicks on any row, the popup is closed and a 'folderAction' event is triggered.

The trigger button icon is disabled if the file is on the root folder.

```vue
<template>
    <FolderAction 
        :filePath="filePath" 
        @folderAction="onFolderSelection" 
        :explanation="Select a folder below">
        <template #icon>
            <IconFolderCancelOutline :size="20" />
        </template>
    </FolderAction>
</template>
```
-->

<template>
    <NcPopover
        :shown="showPopover"
        @update:shown="updateShow"
        popupRole="menu">
        <template #trigger>
            <NcButton 
                :aria-label="explanation"
                :text="explanation"
                size="small"
                variant="tertiary"
                :disabled="!isRootDir(filePath)">
                <template #icon>
                    <!-- @slot icon to be shown as trigger button -->
                    <slot name="icon"></slot>
                </template>
            </NcButton>
        </template>
        <template #default>
            <div class="folder-action-popover">
                <div class="folder-action-popover-heading">
                    {{ explanation }}
                </div>
                <ul>
                    <NcListItem v-for="folder in paths"
                        compact
                        :name="folder"
                        @click="onSelect(folder)">
                        <template #icon>
                            <!-- @slot the same icon is shown in front of each path in the list -->
                            <slot name="icon"></slot>
                        </template>
                    </NcListItem>
                </ul>
            </div>
        </template>
    </NcPopover>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcListItem from '@nextcloud/vue/components/NcListItem'


export default {
    name: 'FolderAction',
    components: {
        NcPopover,
        NcButton,
        NcListItem,
    },
    props: {
        /**
         * The full path of a file, e.g. '/Media/Music/mySong.mp3'
         */
        filePath: {
            type: String,
            default: ''
        },

        /**
         * The text shown at the top of the popup
         */
        explanation: {
            type: String,
            default: ''
        }
    },
    emits: ['folderAction'],
    data() {
        return {
            showPopover: false,
        }
    },
    computed: {
        /**
         * Split the filePath prop into a list of folders
         */
        paths() {
            // filePath has the form "Root/Dir1/Dir2/filename.ext"
            // @returns ["Root/", "Root/Dir1/", "Root/Dir1/Dir2/"]
            const segments = this.filePath.split('/');

            // remove the filename part
            segments.pop(); 

            // return a list where each element is a conjunction of
            // all previous path elements
            const output = segments.map((_, index) => 
                segments.slice(0, index + 1).join('/') + '/');
            return output;
        }
    }, 
    methods: {
        isRootDir(path) {
            return path.includes('/');
        },
        onSelect(path) {
            this.updateShow(false);
            this.$emit('folderAction', path);
        },
        updateShow(event) {
            this.showPopover = event;
        },
    }
};

</script>

<style scoped lang="scss">

.folder-action-popover {
    width: 300px;
    padding: 2px 15px 2px 5px;
}

.folder-action-popover-heading {
    font-weight: bold;
    padding: 5px 15px 0px 5px;
}

</style>
