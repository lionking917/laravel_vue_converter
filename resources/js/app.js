/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

import VueRouter from 'vue-router';
import routes from './routes';
import HomeComponent from "./components/HomeComponent.vue";

Vue.use(VueRouter);

const router = new VueRouter({  routes });

Vue.router = router;

const app = new Vue({ el: '#app', router: router, render: t => t(HomeComponent) });