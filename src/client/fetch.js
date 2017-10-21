import request from 'request';

const buildUrl = (url, path, params) => {
  const keys = Object.keys(params);
  const paramsString = keys.map(key => `${key}=${encodeURIComponent(params[key])}`).join('&');
  return `${url}${path}?${paramsString}`;
};

/**
 * Wrapper on fetch function
 * @param {Object} auth - Authenthication options.
 * @param {String} auth.user - Username.
 * @param {String} auth.pass - Password.
 * @param {('DELETE'|'GET'|'POST'|'PUT')} method - HTTP method.
 * @param {String} path - URL path.
 * @param {?Object} body - Data passed through body object.
 * @returns {Promise} Represents response data.
 */
export default function call(auth, method, path, data) {
  return new Promise(async (resolve, reject) => {
    const options = {
      auth,
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept-Charset': 'utf-8',
        'X-Authorization': this.apiKey,
      },
    };

    if (/POST|PUT/.test(method)) {
      options.body = data && JSON.stringify(data);
      options.url = `${this.apiUrl}${path}`;
    } else if (data) {
      options.url = buildUrl(this.apiUrl, path, data);
    } else {
      options.url = `${this.apiUrl}${path}`;
    }

    request(options, (err, res) => {
      if (err) {
        reject(err);
        return;
      }

      try {
        const json = JSON.parse(res.body);

        if (!/^2/.test(res.statusCode)) {
          const error = { status: res.statusCode, ...json };
          reject(error);
          return;
        }

        resolve(json);
      } catch (error) {
        reject(err);
      }
    });
  });
}
