module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    wp_readme_to_markdown: {
      target: {
      	options: {
      	  screenshot_url: '/.wordpress-org/{screenshot}.png'
        },
        files: {
          'readme.md': 'readme.txt'
        }
      },
      options: {
        screenshot_url: 'https://ps.w.org/tempus-fugit/trunk/{screenshot}.png'
      }
    },
  });

  grunt.loadNpmTasks('grunt-wp-readme-to-markdown');

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown']);

};
