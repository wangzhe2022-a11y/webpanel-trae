/* Monaco Worker bootstrap — sets base URL then loads the bundled worker main.
 * Loaded via getWorkerUrl so Web Workers resolve AMD modules from this origin. */
self.MonacoEnvironment = {
    baseUrl: (self.location && self.location.origin ? self.location.origin : '') + '/static/vendor/monaco/vs/'
};
importScripts(self.MonacoEnvironment.baseUrl + 'base/worker/workerMain.js');
