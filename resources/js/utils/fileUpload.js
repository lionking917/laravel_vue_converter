import * as axios from 'axios';

const BASE_URL = window.location.origin;

function upload(formData) {
    const url = `${BASE_URL}/api/upload-files`;
    return axios.post(url, formData)
        // get data
        .then(x => x.data)
        // add url field
        .then(x => x.map(img => Object.assign({},img, { url: `${BASE_URL}/${img.fileInfo.replace('/var/www/tintworld/', '')}` })));
}

export { upload }