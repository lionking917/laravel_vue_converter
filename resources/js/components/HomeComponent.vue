<template>
    <div class="row justify-content-center ">
        <div class="col-md-8 mt-5">
            <div class="card">
                <div class="card-header text-center">Converter && Translator</div>
                <div class="card-body">
                    <v-select :options="languages" :reduce="language => language.code" label="label" v-model="selectedLanguage" :disabled="isConverting"></v-select>
                    <div class="flex-1 form-ctrl mt-3">
                        <form enctype="multipart/form-data" novalidate >
                            <div class="dropbox">
                                <input type="file" name="file" :disabled="isConverting" @change="filesChange($event.target.files)" accept="*.*" class="input-file">
                                <div class="dropbox-inner">
                                    <div class="img-rt">
                                        <img src="../assets/img/upload.png" alt="upload image">
                                    </div>
                                    <div class="dropbox-content">
                                        <div class="dropbox-cap1">Upload File</div>
                                        <div class="dropbox-cap2">
                                            drag & drop file here, or <span class="f-link">browse</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-primary" @click="uploadFile()" :disabled="!selectedLanguage || formData == null || isConverting" >Upload</button>
                    </div>
                    <div class="mt-3 text-center">
                        <p>{{ message }}</p>
                        <b-spinner v-show="isConverting" variant="primary" label="Spinning"></b-spinner>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import languages from '../languages.js';
    import { fileUpload } from '../utils/fileUtil';

    const STATUS_INITIAL = 0, STATUS_UPLOADING = 1, STATUS_CONVERTING = 2, STATUS_TRANSLATING = 3, STATUS_INVERTING = 4, STATUS_SUCCESS = 5, STATUS_FAILED = 6;

    export default {
        mounted() {
            console.log('Component mounted.');
        },
        data: () => {
            return {
                languages: languages,
                // uploadedFiles: [],
                // uploadError: null,
                isConverting: false,
                selectedLanguage: '',
                formData: null,
                message: '',
                fileId: -1
            }
        },
        methods: {
            reset() {
                // this.currentStatus = STATUS_INITIAL;
                // this.message = '';
                // this.fileId = -1;
                // this.formData = null;
                // this.isConverting = false;
            },
            uploadFile() {
                // this.currentStatus = STATUS_UPLOADING;
                this.message = "File uploading...";
                this.isConverting = true;

                this.formData.append('conv_lang', this.selectedLanguage);
                fileUpload(this.formData)
                .then(res => {
                    if (res.status == 200) {
                        this.fileId = res.data.fileId;
                        this.message = res.data.message;
                    }
                })
                .catch(err => {
                    console.log(err);
                });
            },
            filesChange(fileList) {
                // handle file changes
                this.formData = new FormData();
                if (!fileList.length) return;
                // append the files to FormData
                const file = fileList[0];
                this.formData.append('file', file);
                this.formData.append('file_type', file.type);
                this.formData.append('file_name', file.name);
                this.formData.append('file_size', file.size);
            },
        }
    }
</script>
