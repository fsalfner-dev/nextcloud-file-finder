<!--
This component is a light-weight wrapper around <NcTextField> with a button to delete its content
```vue
<template>
    <SearchInput 
        :modelValue="value" 
        @update="onContentUpdate" 
        @enter="onSubmit" 
        :label="The text shown in the input field" />
</template>
```
-->
<template>
    <div class="search-input">
        <NcTextField :value="modelValue"
            @input="onInput"
            @keydown.enter="hitEnter"
            :label="label"
            trailing-button-icon="close"
            :show-trailing-button="modelValue !== ''"
            @trailing-button-click="clearText">
            <template #icon>
                <Magnify :size="20" />
            </template>
        </NcTextField>    
    </div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'SearchInput',
    props: {
        /**
         * the input field's value
         */
        modelValue: {
            type: String,
            default: ''
        },

        /**
         * the string shown as placeholder / label
         */
        label: {
            type: String,
            default: ''
        },
    },
    emits: ['update', 'enter'],

	components: {
		NcTextField,
        Magnify,
        Close
    },
	methods: {

        /**
         * handle the click on the delete cross
         */
		clearText() {
			this.modelValue = '';
            this.$emit('update', '');
		},

        /**
         * handler for input events
         * @param event the keyboard event
         */
        onInput(event) {
            this.$emit('update', event.target.value)
        },

        /**
         * handler for the user hitting enter
         */
        hitEnter() {
            this.$emit('enter', '');
        }
	}
}
</script>

<style scoped>
.search-input {
    width: 100%;
    display: flex;
    justify-content: center;
    padding-bottom: 4px;
    padding-left: 8px;
    padding-right: 8px;
}

</style>