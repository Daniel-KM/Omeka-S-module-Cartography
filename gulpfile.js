'use strict';

const del = require('del');
const gulp = require('gulp');
const gulpif = require('gulp-if');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');

const bundles = [
    {
        'source': 'node_modules/leaflet/dist/**',
        'dest': 'asset/vendor/leaflet',
    },
    {
        'source': 'node_modules/leaflet-draw/dist/**',
        'dest': 'asset/vendor/leaflet-draw',
    },
    {
        'source': 'node_modules/leaflet-fullscreen/dist/**',
        'dest': 'asset/vendor/leaflet-fullscreen',
    },
    {
        'source': 'node_modules/leaflet-geosearch/dist/**',
        'dest': 'asset/vendor/leaflet-geosearch',
    },
    {
        'source': 'node_modules/leaflet-geosearch/assets/css/**',
        'dest': 'asset/vendor/leaflet-geosearch',
    },
    {
        'source': 'node_modules/leaflet-groupedlayercontrol/dist/**',
        'dest': 'asset/vendor/leaflet-groupedlayercontrol',
    },
    {
        'source': 'node_modules/leaflet.markercluster/dist/**',
        'dest': 'asset/vendor/leaflet-markercluster',
    },
    // Upgraded, but not yet pushed upstream and not in npm (on leaflet only).
    // {
    //     'source': 'node_modules/leaflet-paste/**',
    //     'dest': 'asset/vendor/leaflet-paste',
    // },
    {
        'source': 'node_modules/leaflet-providers/leaflet-providers.js',
        'dest': 'asset/vendor/leaflet-providers',
        'rename': true,
        'uglify': true,
    },
    // Currently customized directly.
    // {
    //     'source': 'node_modules/leaflet-styleeditor/dist/**',
    //     'dest': 'asset/vendor/leaflet-styleeditor',
    // },
    // TODO Check if original works.
    // {
    //     'source': 'node_modules/leaflet-tilelayer-wmts/dist/**',
    //     'dest': 'asset/vendor/leaflet-tilelayer-wmts',
    // },
    {
        'source': 'node_modules/terraformer/terraformer.js',
        'dest': 'asset/vendor/terraformer',
        'rename': true,
        'uglify': true,
    },
    {
        'source': 'node_modules/terraformer-arcgis-parser/terraformer-arcgis-parser.js',
        'dest': 'asset/vendor/terraformer-arcgis-parser',
        'rename': true,
        'uglify': true,
    },
    {
        'source': 'node_modules/terraformer-wkt-parser/terraformer-wkt-parser.min.js',
        'dest': 'asset/vendor/terraformer-wkt-parser',
    },
    {
        'source': 'node_modules/webui-popover/dist/**',
        'dest': 'asset/vendor/webui-popover',
    },
];

gulp.task('clean', function(done) {
    bundles.map(function (bundle) {
        return del(bundle.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundles.map(function (bundle) {
        return gulp.src(bundle.source)
            .pipe(gulpif(bundle.rename, rename({suffix:'.min'})))
            .pipe(gulpif(bundle.uglify, uglify()))
            .pipe(gulp.dest(bundle.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
