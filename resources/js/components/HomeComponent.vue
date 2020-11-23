<template>
    <div class="row justify-content-center ">
        <div class="col-md-8 mt-5">
            <div class="card">
                <div class="card-header text-center">Converter && Translator</div>
                <div class="card-body">
                    <v-select class="lang-select" :options="languages" :reduce="language => language.code" label="label" v-model="targetLanguage" :disabled="isConverting" placeholder="Select target language"></v-select>
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
                        <button type="button" class="btn btn-primary" @click="uploadFile()" :disabled="!targetLanguage || formData == null || isConverting" >Upload</button>
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
    import { fileUpload, fileConvert, jobCheck, fileDownload } from '../utils/fileUtil';
    import { wait } from '../utils/otherUtil';

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
                targetLanguage: '',
                formData: null,
                message: '',
                uFileId: -1,
                jobId: -1,
                timer: null,
                targetFiles: []
            }
        },
        methods: {
            reset() {
                // this.currentStatus = STATUS_INITIAL;
                // this.message = '';
                // this.uFileId = -1;
                // this.formData = null;
                // this.isConverting = false;
            },
            uploadFile() {
                this.isConverting = true;
                // this.currentStatus = STATUS_UPLOADING;
                this.message = "File uploading to server...";

                this.formData.append('toLang', this.targetLanguage);
                fileUpload(this.formData)
                .then(res => {
                    if (res.status == 200) {
                        this.uFileId = res.data.uFileId;
                        this.message = res.data.message;
                        this.convertFile();
                    }
                })
                .catch(err => {
                    this.message = 'File uploading to server failed. Try again to upload file.';
                    this.isConverting = false;
                });
            },
            convertFile() {
                this.message = "File uploading to ZamZar...";

                const formData = new FormData();
                formData.append('uFileId', this.uFileId);
                fileConvert(formData)
                .then(res => {
                    if (res.status == 200) {
                        this.jobId = res.data.jobId;
                        this.message = res.data.message;
                        if (this.timer) clearInterval(this.timer);
                        this.timer = setInterval(this.checkJob, 2500);
                    }
                })
                .catch(err => {
                    console.log(err);
                    this.message = 'File uploading to Zamzar failed. Zamzar job not started. Try again to upload file.';
                    this.isConverting = false;
                });
            },
            checkJob() {
                this.message = "Checking Zamzar job is finished...";

                const formData = new FormData();
                formData.append('uFileId', this.uFileId);
                formData.append('jobId', this.jobId);
                jobCheck(formData)
                .then(res => {
                    if (res.status == 200) {
                        this.message = res.data.message;
                        const status = res.data.status;

                        if (status == 'successful') {
                            clearInterval(this.timer);
                            this.targetFiles = res.data.targetFiles;
                            this.downloadFiles();
                        }
                    }
                })
                .catch(err => {
                    console.log(err);
                    this.message = 'Zamzar job failed. Try again to upload file.';
                    this.isConverting = false;
                    clearInterval(this.timer);
                });
            },
            downloadFiles() {
                this.targetFiles.forEach(async (targetFile) => {
                    try {
                        this.message = `Started downloading zamzar file${targetFile['name']} to server...`;
                        const formData = new FormData();
                        formData.append('uFileId', this.uFileId);
                        formData.append('targetFile', targetFile);
                        const res = await fileDownload(formData);
                        if (res.status == 200) {
                            this.message = res.data.message;
                            wait(1000);
                        }
                    } catch (err) {
                        console.log(err);
                        this.message = 'Downloading zamzar files to server failed. Try upload file again.';
                        this.isConverting = false;
                    }
                });
            },
            filesChange(fileList) {
                // handle file changes
                this.formData = new FormData();
                if (!fileList.length) return;
                // append the files to FormData
                const file = fileList[0];
                this.formData.append('file', file);
                this.formData.append('fileType', file.type);
                this.formData.append('fileName', file.name);
                this.formData.append('fileSize', file.size);
            },
        }
    }
</script>
