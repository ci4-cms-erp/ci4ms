/* eslint-env node */

var license =
        '/*!\n' +
        '* jQuery Password Strength plugin for Twitter Bootstrap\n' +
        '* Version: <%= pkg.version %>\n' +
        '*\n' +
        '* Copyright (c) 2008-2013 Tane Piper\n' +
        '* Copyright (c) 2013 Alejandro Blanco\n' +
        '* Dual licensed under the MIT and GPL licenses.\n' +
        '*/\n\n' +
        '(function (jQuery) {\n',
    eslintConfig = {
        target: ['src/*js', 'spec/*js', 'Gruntfile.js']
    },
    jasmineConfig = {
        options: {
            forceExit: true,
            jUnit: {
                report: false
            }
        },
        all: ['spec/']
    },
    concatConfig = {
        options: {
            banner: license,
            footer: '}(jQuery));',
            process: function(src, filepath) {
                // Remove ALL block comments, the stripBanners only removes
                // the first one
                src = src.replace(/\/\*[\s\S]*?\*\//g, '');
                return '// Source: ' + filepath + src;
            }
        },
        dist: {
            src: [
                'src/i18n.js',
                'src/rules.js',
                'src/options.js',
                'src/ui.js',
                'src/ui.progressbar.js',
                'src/ui.popover.js',
                'src/methods.js'
            ],
            dest: '<%= pkg.name %>.js'
        }
    },
    uglifyConfig = {
        options: {
            banner:
                '/* <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> - GPLv3 & MIT License */\n',
            sourceMap: true,
            sourceMapName: '<%= pkg.name %>.min.map'
        },
        dist: {
            files: {
                '<%= pkg.name %>.min.js': ['<%= concat.dist.dest %>']
            }
        }
    },
    shellConfig = {
        copyFile: {
            command: 'cp <%= concat.dist.dest %> examples/pwstrength.js'
        },
        copyZxcvbn: {
            command:
                'cp bower_components/zxcvbn/dist/zxcvbn.js examples/zxcvbn.js'
        },
        copyI18next: {
            command:
                'cp bower_components/i18next/i18next.min.js examples/i18next.js'
        },
        makeDir: {
            command: 'mkdir -p dist'
        },
        moveFiles: {
            command: 'mv <%= pkg.name %>* dist/'
        }
    };

module.exports = function(grunt) {
    'use strict';

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        eslint: eslintConfig,
        // eslint-disable-next-line camelcase
        jasmine_node: jasmineConfig,
        concat: concatConfig,
        uglify: uglifyConfig,
        shell: shellConfig
    });

    // Load the plugins
    grunt.loadNpmTasks('grunt-eslint');
    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-jasmine-node');

    grunt.registerTask('test', ['eslint', 'jasmine_node']);

    // Default task(s)
    grunt.registerTask('default', ['eslint', 'concat', 'uglify', 'shell']);
};
