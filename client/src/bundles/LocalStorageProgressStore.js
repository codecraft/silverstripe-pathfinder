require('nodelist-foreach-polyfill');

const storageName = '$StorageName';

/**
 * @param {string} encoded
 */
const decodeData = (encoded) => JSON.parse(atob(decodeURIComponent(encoded)));

/**
 * @param {string} variable
 * @return {mixed} The request variable's value
 */
const getQueryVariable = (variable) => {
  const query = window.location.search.substring(1);
  const getVars = query.split('&');
  let match = false;

  getVars.forEach((getVar) => {
    const pair = getVar.split('=');

    if (pair[0] === variable) {
      match = pair[1];
    }
  });

  return match;
};

/**
 * @param {string} progress A url encoded state of progress
 * @return {object|bool}
 */
const decodeProgress = (encoded) => {
  if (!encoded) {
    return false;
  }

  const progress = decodeData(encoded);

  if (typeof progress !== 'object') {
    // eslint-disable-next-line no-console
    console.warn('Unexpected value supplied for decoding progress');

    return false;
  }

  return progress;
};

/**
 * The default component
 */
const LocalStorageProgressStore = () => {
  // This pattern uses an id and a timestamp to "sync" with the back end.
  // When the request provides an encoded copy of the progress data, we verify
  // to see if it's later than what is stored locally, before storing it.
  // If it's not the latest, we redirect to update the request to the latest store

  const encodedRequestProgress = getQueryVariable('progress');
  const encodedLocalProgress = window.localStorage.getItem(storageName);

  if (encodedRequestProgress) {
    const requestProgress = decodeProgress(encodedRequestProgress);
    const localProgress = decodeProgress(encodedLocalProgress);

    if (!localProgress || requestProgress.timestamp >= localProgress.timestamp) {
      // Sync down the latest store
      window.localStorage.setItem(storageName, encodedRequestProgress);

      // Nothing else to do
      return;
    }
  }

  if (!encodedLocalProgress) {
    // Nothing to do
    return;
  }

  // We want to update the user with the latest
  // Let's swap out the ?progress param as safely as we can
  const loc = window.location;
  const queryParts = [];

  // Use substring to remove the leading '?'
  loc.search.substring(1).split('&').forEach((item) => {
    if (!item.length) {
      return;
    }

    const param = item.split('=');

    if (param[0] === 'progress') {
      // Skip, so we can rewrite this param below
      return;
    }

    queryParts.push(item);
  });

  // Add our own progress param
  queryParts.push(`progress=${encodedLocalProgress}`);

  // Redirect to the latest store
  window.location = [loc.origin, loc.pathname, '?', queryParts.join('&')].join('');
};

window.addEventListener('load', () => {
  // Instantiate component
  LocalStorageProgressStore();
});
