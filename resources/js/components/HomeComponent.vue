<template>
    <div class="row justify-content-center ">
        <div class="col-md-8 mt-5">
            <div class="card">
                <div class="card-header text-center">Converter && Translator</div>
                <div class="card-body">
                    <v-select :options="languages" :reduce="language => language.code" label="label" v-model="selectedLanguage"></v-select>
                    <div class="flex-1 form-ctrl mt-3">
                        <form enctype="multipart/form-data" novalidate >
                            <div class="dropbox">
                                <input type="file" multiple name="upload_file" :disabled="isSaving" @change="filesChange($event.target.name, $event.target.files); fileCount = $event.target.files.length"
                                accept="image/*" class="input-file">
                                <div class="dropbox-inner">
                                    <div class="img-rt">
                                        <img src="../assets/img/upload.png" alt="upload image">
                                    </div>
                                    <div class="dropbox-content">
                                        <div class="dropbox-cap1">Upload Files</div>
                                        <div class="dropbox-cap2">
                                            drag & drop images here, or <span class="f-link">browse</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-primary" @click="save()" :disabled="!selectedLanguage || formData == null" >Upload</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import languages from '../languages.js';
    import { upload } from '../utils/fileUpload';
    import { wait } from "../utils/useful"

    const STATUS_INITIAL = 0, STATUS_UPLOADING = 1, STATUS_CONVERTING = 2, STATUS_TRANSLATING = 3, STATUS_INVERTING = 4, STATUS_SUCCESS = 5, STATUS_FAILED = 6;

    export default {
        mounted() {
            console.log('Component mounted.')
        },
        data: () => {
            return {
                languages: languages,
                uploadedFiles: [],
                uploadError: null,
                isSaving: false,
                selectedLanguage: '',
                formData: null
            }
        },
        methods: {
            reset() {
                // reset form to initial state
                this.currentStatus = STATUS_INITIAL;
                this.uploadedFiles = [];
                this.uploadError = null;
            },
            save() {
                // upload data to the server
                this.currentStatus = STATUS_UPLOADING;
                upload(this.formData)
                .then(wait(1500)) // DEV ONLY: wait for 1.5s 
                .then(x => {
                    this.uploadedFiles = [].concat(x);
                    this.currentStatus = STATUS_SUCCESS;
                })
                .catch(err => {
                    this.uploadError = err.response;
                    this.currentStatus = STATUS_FAILED;
                });
            },
            filesChange(fieldName, fileList) {
                // handle file changes
                this.formData = new FormData();
                if (!fileList.length) return;
                // append the files to FormData
                Array
                .from(Array(fileList.length).keys())
                .map(x => {
                    this.formData.append(fieldName, fileList[x], fileList[x].name);
                });
            },
        }
    }
</script>
