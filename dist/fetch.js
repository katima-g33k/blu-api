'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

exports.default = call;

var _request = require('request');

var _request2 = _interopRequireDefault(_request);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _asyncToGenerator(fn) { return function () { var gen = fn.apply(this, arguments); return new Promise(function (resolve, reject) { function step(key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { return Promise.resolve(value).then(function (value) { step("next", value); }, function (err) { step("throw", err); }); } } return step("next"); }); }; }

var buildUrl = function buildUrl(url, path, params) {
  var keys = Object.keys(params);
  var paramsString = keys.map(function (key) {
    return key + '=' + encodeURIComponent(params[key]);
  }).join('&');
  return '' + url + path + '?' + paramsString;
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
function call(auth, method, path, data) {
  var _this = this;

  return new Promise(function () {
    var _ref = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee(resolve, reject) {
      var options;
      return regeneratorRuntime.wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              options = {
                auth: auth,
                method: method,
                headers: {
                  'Content-Type': 'application/json',
                  'Accept-Charset': 'utf-8',
                  'X-Authorization': _this.apiKey
                }
              };


              if (/POST|PUT/.test(method)) {
                options.body = data && JSON.stringify(data);
                options.url = '' + _this.apiUrl + path;
              } else if (data) {
                options.url = buildUrl(_this.apiUrl, path, data);
              } else {
                options.url = '' + _this.apiUrl + path;
              }

              (0, _request2.default)(options, function (err, res) {
                if (err) {
                  reject(err);
                  return;
                }

                try {
                  var json = JSON.parse(res.body);

                  if (!/^2/.test(res.statusCode)) {
                    var error = _extends({ status: res.statusCode }, json);
                    reject(error);
                    return;
                  }

                  resolve(json);
                } catch (error) {
                  reject(err);
                }
              });

            case 3:
            case 'end':
              return _context.stop();
          }
        }
      }, _callee, _this);
    }));

    return function (_x, _x2) {
      return _ref.apply(this, arguments);
    };
  }());
}