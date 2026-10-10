<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API {

    const REST_NAMESPACE = 'eldokan-customer/v1';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_filter('rest_post_dispatch', [__CLASS__, 'normalize_rest_error'], 10, 3);
    }

    public static function normalize_rest_error($response, $server, $request) {
        if (strpos($request->get_route(), '/' . self::REST_NAMESPACE . '/') !== 0) {
            return $response;
        }
        if (is_wp_error($response)) {
            $response = rest_convert_error_to_response($response);
        }
        if (!($response instanceof WP_REST_Response) || $response->get_status() < 400) {
            return $response;
        }
        $body = $response->get_data();
        if (!is_array($body) || (isset($body['success']) && $body['success'] === false)) {
            return $response;
        }
        $response->set_data([
            'success' => false,
            'error' => [
                'code' => sanitize_key((string) ($body['code'] ?? 'rest_error')),
                'message' => (string) ($body['message'] ?? 'Request failed.'),
            ],
            'meta' => ['request_id' => self::request_id()],
        ]);
        return self::decorate_response($response, 'BYPASS', 0, microtime(true));
    }

    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'health'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'auth_register'],
            'args' => self::registration_args(),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'auth_login'],
            'args' => [
                'email' => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/session', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'auth_session'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'auth_logout'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'account'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'account_update'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/me/addresses', [
            [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'addresses'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'address_create'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/me/addresses/(?P<address_id>[^/]+)', [
            [
                'methods' => 'PATCH',
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'address_update'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'address_remove'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/wishlist', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'wishlist'],
            'args' => self::language_args(),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/wishlist/items', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'wishlist_add'],
            'args' => array_merge(self::language_args(), [
                'product_id' => ['required' => true, 'type' => 'string'],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/wishlist/items/(?P<product_id>prd_\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'wishlist_remove'],
            'args' => array_merge(self::language_args(), [
                'product_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'cart'],
            'args' => self::language_args(),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'cart_add'],
            'args' => array_merge(self::language_args(), [
                'product_id' => ['required' => true, 'type' => 'string'],
                'variation_id' => ['required' => false, 'type' => 'string'],
                'quantity' => ['required' => true],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items/(?P<item_id>cit_[a-f0-9]{32})', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'cart_update'],
                'args' => array_merge(self::language_args(), [
                    'item_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                    'quantity' => ['required' => true],
                ]),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'cart_remove'],
                'args' => array_merge(self::language_args(), [
                    'item_id' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                ]),
            ],
        ]);

        // A2: quote and readiness only. No placement/payment endpoint.
        register_rest_route(self::REST_NAMESPACE, '/checkout', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'checkout'],
            'args' => self::language_args(),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/checkout/quote', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'checkout_quote'],
            'args' => self::language_args(),
        ]);

        // A3 placement routes; no Order read API.
        foreach (['attempts' => 'checkout_attempt', 'orders' => 'checkout_order'] as $path => $callback) {
            register_rest_route(self::REST_NAMESPACE, '/checkout/' . $path, [
                'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => '__return_true',
                'callback' => [__CLASS__, $callback], 'args' => self::language_args(),
            ]);
        }
        // End A3 placement routes.
        // A4 private orders.
        foreach (['/orders'=>['GET','order_list'], '/orders/(?P<order_id>[^/]+)'=>['GET','order_detail'], '/orders/(?P<order_id>[^/]+)/payment'=>['POST','order_payment'], '/orders/(?P<order_id>[^/]+)/cancel'=>['POST','order_cancel']] as $path=>$definition) {
            register_rest_route(self::REST_NAMESPACE, $path, ['methods'=>$definition[0], 'permission_callback'=>'__return_true', 'callback'=>[__CLASS__,$definition[1]], 'args'=>self::language_args()]);
        }
        // End A4 private orders.

        register_rest_route(self::REST_NAMESPACE, '/home', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'home'],
            'args' => self::language_args(),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/categories', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'categories'],
            'args' => array_merge(self::language_args(), [
                'parent' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_title',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/categories/(?P<slug>[^/]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'category'],
            'args' => array_merge(self::language_args(), [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_title',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/categories/(?P<slug>[^/]+)/filters', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'category_filters'],
            'args' => array_merge(self::language_args(), [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_title',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/brands', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'brands'],
            'args' => array_merge(
                self::language_args(),
                self::term_collection_args()
            ),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/tags', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'tags'],
            'args' => array_merge(
                self::language_args(),
                self::term_collection_args()
            ),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/sellers/(?P<seller_id>sel_\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'seller'],
            'args' => array_merge(self::language_args(), [
                'seller_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/products', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'products'],
            'args' => array_merge(self::language_args(), [
                'page' => [
                    'required' => false,
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required' => false,
                    'default' => 24,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'category' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_title',
                ],
                'brand' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_title',
                ],
                'tag' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_title',
                ],
                'attributes' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'min_price' => [
                    'required' => false,
                    'sanitize_callback' => ['ElDokan_Customer_API_Utils', 'decimal'],
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (float) $value >= 0;
                    },
                ],
                'max_price' => [
                    'required' => false,
                    'sanitize_callback' => ['ElDokan_Customer_API_Utils', 'decimal'],
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (float) $value >= 0;
                    },
                ],
                'stock_status' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['in_stock', 'out_of_stock', 'on_backorder'], true);
                    },
                ],
                'on_sale' => [
                    'required' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'featured' => [
                    'required' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'sort' => [
                    'required' => false,
                    'default' => 'newest',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['newest', 'price_asc', 'price_desc', 'best_selling', 'rating', 'relevance'], true);
                    },
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/products/lookup', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'product_lookup'],
            'args' => array_merge(self::language_args(), [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_title',
                    'validate_callback' => function ($value) {
                        return is_string($value) && $value !== '';
                    },
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/products/(?P<product_id>prd_\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'product'],
            'args' => array_merge(self::language_args(), [
                'product_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]),
        ]);

        register_rest_route(self::REST_NAMESPACE, '/search/suggestions', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'search_suggestions'],
            'args' => array_merge(self::language_args(), [
                'q' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required' => false,
                    'default' => 8,
                    'sanitize_callback' => 'absint',
                ],
            ]),
        ]);
    }

    private static function language_args() {
        return [
            'lang' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function ($value) {
                    return $value === null || $value === '' || in_array($value, ['ar', 'en'], true);
                },
            ],
        ];
    }

    private static function registration_args() {
        return [
            'email' => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
            'first_name' => ['required' => false, 'type' => 'string'],
            'last_name' => ['required' => false, 'type' => 'string'],
            'display_name' => ['required' => false, 'type' => 'string'],
            'phone' => ['required' => false, 'type' => 'string'],
        ];
    }

    private static function term_collection_args() {
        return [
            'page' => [
                'required' => false,
                'default' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return self::valid_positive_integer($value);
                },
            ],
            'per_page' => [
                'required' => false,
                'default' => 50,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return self::valid_positive_integer($value, 100);
                },
            ],
            'search' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private static function valid_positive_integer($value, $maximum = null) {
        $value = (string) $value;

        if (!preg_match('/^[1-9][0-9]*$/', $value)) {
            return false;
        }

        $number = (int) $value;

        return $maximum === null || $number <= (int) $maximum;
    }

    public static function health(WP_REST_Request $request) {
        $started = microtime(true);
        $ready = class_exists('WooCommerce') && function_exists('wc_get_product');

        $data = [
            'service' => 'ElDokan Customer API',
            'version' => ELDOKAN_CUSTOMER_API_VERSION,
            'status' => $ready ? 'ok' : 'degraded',
            'ready' => $ready,
        ];

        return self::decorate_response(
            self::success($data),
            'BYPASS',
            0,
            $started
        );
    }

    public static function auth_register(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Auth::register($request), 201);
    }

    public static function auth_login(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Auth::login($request));
    }

    public static function auth_session(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Auth::session($request));
    }

    public static function auth_logout(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Auth::logout($request));
    }

    public static function account(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Account::get($request));
    }

    public static function account_update(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Account::update($request));
    }

    public static function addresses(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Addresses::get($request));
    }

    public static function address_create(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Addresses::create($request), 201);
    }

    public static function address_update(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Addresses::update($request));
    }

    public static function address_remove(WP_REST_Request $request) {
        return self::private_result(ElDokan_Customer_API_Addresses::remove($request));
    }

    public static function wishlist(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Wishlist::get($request));
        });
    }

    public static function wishlist_add(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Wishlist::add($request));
        });
    }

    public static function wishlist_remove(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Wishlist::remove($request));
        });
    }

    public static function cart(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Cart::get($request));
        });
    }

    public static function cart_add(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Cart::add($request));
        });
    }

    public static function cart_update(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Cart::update($request));
        });
    }

    public static function cart_remove(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Cart::remove($request));
        });
    }

    public static function checkout(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Checkout::get($request));
        });
    }

    public static function checkout_quote(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::private_result(ElDokan_Customer_API_Checkout::quote($request));
        });
    }

    // A3 placement callbacks.
    public static function checkout_attempt(WP_REST_Request $request) {
        return self::run_localized($request, static function () use ($request) {
            return self::placement_result(ElDokan_Customer_API_Orders::attempt($request), 201);
        });
    }
    public static function checkout_order(WP_REST_Request $request) {
        return self::run_localized($request, static function () use ($request) {
            return self::placement_result(ElDokan_Customer_API_Order_Read::placed($request));
        });
    }
    private static function placement_result($result, $status = 200) {
        $response = self::private_result($result, $status);
        if (is_wp_error($result)) {
            $details = $result->get_error_data();
            if (is_array($details) && isset($details['issues']) && is_array($details['issues'])) {
                $body = $response->get_data();
                $body['error']['issues'] = $details['issues'];
                $response->set_data($body);
            }
        }
        return $response;
    }
    // End A3 placement callbacks.
    // A4 private order callbacks.
    public static function order_list(WP_REST_Request $request) { return self::run_localized($request, static function()use($request){return self::placement_result(ElDokan_Customer_API_Order_Read::listing($request));}); }
    public static function order_detail(WP_REST_Request $request) { return self::run_localized($request, static function()use($request){return self::placement_result(ElDokan_Customer_API_Order_Read::get($request));}); }
    public static function order_payment(WP_REST_Request $request) { return self::run_localized($request, static function()use($request){return self::placement_result(ElDokan_Customer_API_Order_Read::pay($request));}); }
    public static function order_cancel(WP_REST_Request $request) { return self::run_localized($request, static function()use($request){return self::placement_result(ElDokan_Customer_API_Order_Cancellation::cancel($request));}); }
    // End A4 private order callbacks.

    public static function home(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'home',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Home::get($request);
                }
            );
        });
    }

    public static function categories(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'categories',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Categories::list($request);
                }
            );
        });
    }

    public static function category(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'category',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Categories::single(
                        $request['slug']
                    );
                }
            );
        });
    }

    public static function category_filters(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'category_filters',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Categories::filters(
                        $request['slug']
                    );
                }
            );
        });
    }

    public static function brands(WP_REST_Request $request) {
        return self::term_collection_response(
            'brands',
            $request,
            function () use ($request) {
                return ElDokan_Customer_API_Taxonomies::brands($request);
            }
        );
    }

    public static function tags(WP_REST_Request $request) {
        return self::term_collection_response(
            'tags',
            $request,
            function () use ($request) {
                return ElDokan_Customer_API_Taxonomies::tags($request);
            }
        );
    }

    public static function seller(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'seller',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Sellers::by_public_id(
                        $request['seller_id']
                    );
                }
            );
        });
    }

    public static function products(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'products',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Products::list($request);
                },
                function ($result) {
                    return self::success(
                        $result['items'],
                        [
                            'page' => $result['page'],
                            'per_page' => $result['per_page'],
                            'total' => $result['total'],
                            'total_pages' => $result['total_pages'],
                        ]
                    );
                }
            );
        });
    }

    public static function product(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'product',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Products::single_by_public_id(
                        $request['product_id'],
                        ElDokan_Customer_API_Utils::requested_language($request)
                    );
                }
            );
        });
    }

    public static function product_lookup(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'product_lookup',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Products::single_by_slug(
                        $request->get_param('slug'),
                        ElDokan_Customer_API_Utils::requested_language($request)
                    );
                }
            );
        });
    }

    public static function search_suggestions(WP_REST_Request $request) {
        return self::run_localized($request, function () use ($request) {
            return self::cached_response(
                'search_suggestions',
                $request,
                function () use ($request) {
                    return ElDokan_Customer_API_Products::suggestions(
                        $request
                    );
                }
            );
        });
    }

    private static function term_collection_response(
        $scope,
        WP_REST_Request $request,
        $producer
    ) {
        return self::run_localized($request, function () use (
            $scope,
            $request,
            $producer
        ) {
            return self::cached_response(
                $scope,
                $request,
                $producer,
                function ($result) {
                    return self::success(
                        $result['items'],
                        [
                            'page' => $result['page'],
                            'per_page' => $result['per_page'],
                            'total' => $result['total'],
                            'total_pages' => $result['total_pages'],
                        ]
                    );
                }
            );
        });
    }

    private static function cached_response(
        $scope,
        WP_REST_Request $request,
        $producer,
        $formatter = null
    ) {
        $started = microtime(true);

        $cached = ElDokan_Customer_API_Cache::remember(
            $scope,
            $request,
            $producer
        );

        $value = $cached['value'];

        if (is_wp_error($value)) {
            $response = self::from_error($value);
        } elseif (is_callable($formatter)) {
            $response = call_user_func($formatter, $value);
        } else {
            $response = self::success($value);
        }

        return self::decorate_response(
            $response,
            $cached['status'],
            $cached['ttl'],
            $started,
            isset($cached['diagnostics']) && is_array($cached['diagnostics'])
                ? $cached['diagnostics']
                : []
        );
    }

    private static function private_result($result, $status = 200) {
        $started = microtime(true);
        $response = is_wp_error($result)
            ? self::from_error($result)
            : self::success($result, [], $status);

        return self::decorate_response($response, 'BYPASS', 0, $started);
    }

    public static function success($data, $meta = [], $status = 200) {
        $meta = array_merge([
            'request_id' => self::request_id(),
        ], $meta);

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ], absint($status) ?: 200);
    }

    public static function from_error(WP_Error $error) {
        $data = $error->get_error_data();
        $status = is_array($data) && !empty($data['status']) ? absint($data['status']) : 400;
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => $error->get_error_code(),
                'message' => $error->get_error_message(),
            ],
            'meta' => [
                'request_id' => self::request_id(),
            ],
        ], $status);
    }

    private static function decorate_response(
        WP_REST_Response $response,
        $cache_status,
        $cache_ttl,
        $started,
        $cache_diagnostics = []
    ) {
        $duration_ms = max(
            0,
            round((microtime(true) - (float) $started) * 1000, 1)
        );

        $response->header(
            'X-ElDokan-API-Version',
            ELDOKAN_CUSTOMER_API_VERSION
        );
        // Until edge cache has a tested language/currency key, cache only in PHP.
        $response->header('Cache-Control', 'private, no-store');
        $response->header(
            'X-ElDokan-Request-ID',
            self::request_id()
        );
        $response->header(
            'X-ElDokan-Cache',
            (string) $cache_status
        );
        $response->header(
            'X-ElDokan-Cache-TTL',
            (string) absint($cache_ttl)
        );
        $response->header(
            'Content-Language',
            ElDokan_Customer_API_Language::effective()
        );
        $response->header(
            'Server-Timing',
            'eldokan;dur=' . $duration_ms
        );
        if (!empty($cache_diagnostics)) {
            $response->header(
                'X-ElDokan-Cache-Generation',
                (string) ($cache_diagnostics['generation'] ?? '')
            );
            $response->header(
                'X-ElDokan-Cache-Key',
                (string) ($cache_diagnostics['key_hash'] ?? '')
            );
            $response->header(
                'X-ElDokan-Cache-Backend',
                (string) ($cache_diagnostics['backend'] ?? '')
            );
            $response->header(
                'X-ElDokan-Cache-Store',
                (string) ($cache_diagnostics['store'] ?? '')
            );
        }

        $response->header(
            'Access-Control-Expose-Headers',
            'X-ElDokan-API-Version, X-ElDokan-Request-ID, X-ElDokan-Cache, X-ElDokan-Cache-TTL, X-ElDokan-Cache-Generation, X-ElDokan-Cache-Key, X-ElDokan-Cache-Backend, X-ElDokan-Cache-Store, Content-Language, Server-Timing'
        );

        return $response;
    }

    private static function request_id() {
        static $request_id = null;

        if ($request_id === null) {
            $request_id = 'req_' . str_replace('-', '', wp_generate_uuid4());
        }

        return $request_id;
    }

    private static function run_localized(WP_REST_Request $request, $callback) {
        return ElDokan_Customer_API_Language::run($request, $callback);
    }
}
