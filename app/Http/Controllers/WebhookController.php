<?php

namespace App\Http\Controllers;

use App\Mail\PaymentMail;
use App\Models\Coupon;
use App\Models\Payment;
use App\Traits\PaymentTrait;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    use PaymentTrait;

    /**
     * Handle the PayPal webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function paypal(Request $request)
    {
        $httpClient = new HttpClient();

        $httpBaseUrl = 'https://'.(config('settings.paypal_mode') == 'sandbox' ? 'api-m.sandbox' : 'api-m').'.paypal.com/';

        // Attempt to retrieve the auth token
        try {
            $payPalAuthRequest = $httpClient->request('POST', $httpBaseUrl . 'v1/oauth2/token', [
                    'auth' => [config('settings.paypal_client_id'), config('settings.paypal_secret')],
                    'form_params' => [
                        'grant_type' => 'client_credentials'
                    ]
                ]
            );

            $payPalAuth = json_decode($payPalAuthRequest->getBody()->getContents());
        } catch (BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return response()->json([
                'status' => 400
            ], 400);
        }

        // Get the payload's content
        $payload = json_decode($request->getContent());

        // Attempt to validate the webhook signature
        try {
            $payPalWHSignatureRequest = $httpClient->request('POST', $httpBaseUrl . 'v1/notifications/verify-webhook-signature', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $payPalAuth->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                        'cert_url' => $request->header('PAYPAL-CERT-URL'),
                        'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                        'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                        'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                        'webhook_id' => config('settings.paypal_webhook_id'),
                        'webhook_event' => $payload
                    ])
                ]
            );

            $payPalWHSignature = json_decode($payPalWHSignatureRequest->getBody()->getContents());
        } catch (BadResponseException $e) {
            Log::info($e->getResponse()->getBody()->getContents());

            return response()->json([
                'status' => 400
            ], 400);
        }

        // Check if the webhook's signature status is successful
        if ($payPalWHSignature->verification_status != 'SUCCESS') {
            Log::info('PayPal signature validation failed.');

            return response()->json([
                'status' => 400
            ], 400);
        }

        // Parse the custom metadata parameters
        parse_str($payload->resource->custom_id ?? ($payload->resource->custom ?? null), $metadata);

        if ($metadata) {
            $user = User::where('id', '=', $metadata['user'])->first();

            // If a user was found
            if ($user) {
                if ($payload->event_type == 'BILLING.SUBSCRIPTION.CREATED') {
                    // If the user previously had a subscription, attempt to cancel it
                    if ($user->plan_subscription_id) {
                        $user->planSubscriptionCancel();
                    }

                    $user->plan_id = $metadata['plan'];
                    $user->plan_amount = $metadata['amount'];
                    $user->plan_currency = $metadata['currency'];
                    $user->plan_interval = $metadata['interval'];
                    $user->plan_payment_processor = 'paypal';
                    $user->plan_subscription_id = $payload->resource->id;
                    $user->plan_subscription_status = $payload->resource->status;
                    $user->plan_subscription_information = null;
                    $user->plan_created_at = Carbon::now();
                    $user->plan_recurring_at = null;
                    $user->plan_ends_at = null;
                    $user->save();

                    // If a coupon was used
                    if (isset($metadata['coupon']) && $metadata['coupon']) {
                        $coupon = Coupon::find($metadata['coupon']);

                        // If a coupon was found
                        if ($coupon) {
                            // Increase the coupon usage
                            $coupon->increment('redeems', 1);
                        }
                    }
                } elseif (stripos($payload->event_type, 'BILLING.SUBSCRIPTION.') !== false) {
                    // If the subscription exists
                    if ($user->plan_payment_processor == 'paypal' && $user->plan_subscription_id == $payload->resource->id) {
                        // Update the recurring date
                        if (isset($payload->resource->billing_info->next_billing_time)) {
                            $user->plan_recurring_at = Carbon::create($payload->resource->billing_info->next_billing_time);
                        }

                        // Update the subscription status
                        if (isset($payload->resource->status)) {
                            $user->plan_subscription_status = $payload->resource->status;
                        }

                        // If the subscription has been cancelled
                        if ($payload->event_type == 'BILLING.SUBSCRIPTION.CANCELLED') {
                            // Update the subscription end date and recurring date
                            if (!empty($user->plan_recurring_at)) {
                                $user->plan_ends_at = $user->plan_recurring_at;
                                $user->plan_recurring_at = null;
                            }
                        }

                        $user->save();
                    }
                } elseif ($payload->event_type == 'PAYMENT.SALE.COMPLETED') {
                    // If the payment does not exist
                    if (!Payment::where([['processor', '=', 'paypal'], ['payment_id', '=', $payload->resource->id]])->exists()) {
                        $payment = $this->paymentStore([
                            'user_id' => $user->id,
                            'plan_id' => $metadata['plan'],
                            'payment_id' => $payload->resource->id,
                            'processor' => 'paypal',
                            'amount' => $metadata['amount'],
                            'currency' => $metadata['currency'],
                            'interval' => $metadata['interval'],
                            'status' => 'completed',
                            'coupon' => $metadata['coupon'] ?? null,
                            'tax_rates' => $metadata['tax_rates'] ?? null,
                            'customer' => $user->billing_information,
                        ]);

                        // Attempt to send the payment confirmation email
                        try {
                            Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                        }
                        catch (\Exception $e) {}
                    }
                }
            }
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Stripe webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function stripe(Request $request)
    {
        // Attempt to validate the Webhook
        try {
            $stripeEvent = \Stripe\Webhook::constructEvent($request->getContent(), $request->server('HTTP_STRIPE_SIGNATURE'), config('settings.stripe_wh_secret'));
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            Log::info($e->getMessage());

            return response()->json([
                'status' => 400
            ], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::info($e->getMessage());

            return response()->json([
                'status' => 400
            ], 400);
        }

        // Get the metadata
        $metadata = $stripeEvent->data->object->lines->data[0]->metadata ?? ($stripeEvent->data->object->metadata ?? null);

        if (isset($metadata->user)) {
            if ($stripeEvent->type != 'customer.subscription.created' && stripos($stripeEvent->type, 'customer.subscription.') !== false) {
                // Provide enough time for the subscription created event to be handled
                sleep(3);
            }

            $user = User::where('id', '=', $metadata->user)->first();

            // If a user was found
            if ($user) {
                if ($stripeEvent->type == 'customer.subscription.created') {
                    // If the user previously had a subscription, attempt to cancel it
                    if ($user->plan_subscription_id) {
                        $user->planSubscriptionCancel();
                    }

                    $user->plan_id = $metadata->plan;
                    $user->plan_amount = $metadata->amount;
                    $user->plan_currency = $metadata->currency;
                    $user->plan_interval = $metadata->interval;
                    $user->plan_payment_processor = 'stripe';
                    $user->plan_subscription_id = $stripeEvent->data->object->id;
                    $user->plan_subscription_status = $stripeEvent->data->object->status;
                    $user->plan_subscription_information = null;
                    $user->plan_created_at = Carbon::now();
                    $user->plan_recurring_at = $stripeEvent->data->object->current_period_end ? Carbon::createFromTimestamp($stripeEvent->data->object->current_period_end) : null;
                    $user->plan_ends_at = null;
                    $user->save();

                    // If a coupon was used
                    if (isset($metadata->coupon) && $metadata->coupon) {
                        $coupon = Coupon::find($metadata->coupon);

                        // If a coupon was found
                        if ($coupon) {
                            // Increase the coupon usage
                            $coupon->increment('redeems', 1);
                        }
                    }
                } elseif (stripos($stripeEvent->type, 'customer.subscription.') !== false) {
                    // If the subscription exists
                    if ($user->plan_payment_processor == 'stripe' && $user->plan_subscription_id == $stripeEvent->data->object->id) {
                        // Update the recurring date
                        if ($stripeEvent->data->object->current_period_end) {
                            $user->plan_recurring_at = Carbon::createFromTimestamp($stripeEvent->data->object->current_period_end);
                        }

                        // Update the subscription status
                        if ($stripeEvent->data->object->status) {
                            $user->plan_subscription_status = $stripeEvent->data->object->status;
                        }

                        // Update the subscription end date
                        if ($stripeEvent->data->object->cancel_at_period_end) {
                            $user->plan_ends_at = Carbon::createFromTimestamp($stripeEvent->data->object->current_period_end);
                        } elseif ($stripeEvent->data->object->cancel_at) {
                            $user->plan_ends_at = Carbon::createFromTimestamp($stripeEvent->data->object->cancel_at);
                        } elseif ($stripeEvent->data->object->canceled_at) {
                            $user->plan_ends_at = Carbon::createFromTimestamp($stripeEvent->data->object->canceled_at);
                        } else {
                            $user->plan_ends_at = null;
                        }

                        // Reset the subscription recurring date
                        if (!empty($user->plan_ends_at)) {
                            $user->plan_recurring_at = null;
                        }

                        $user->save();
                    }
                } elseif ($stripeEvent->type == 'invoice.paid') {
                    // Make sure the invoice contains the payment id
                    if ($stripeEvent->data->object->charge) {
                        // If the payment does not exist
                        if (!Payment::where([['processor', '=', 'stripe'], ['payment_id', '=', $stripeEvent->data->object->charge]])->exists()) {
                            $payment = $this->paymentStore([
                                'user_id' => $user->id,
                                'plan_id' => $metadata->plan,
                                'payment_id' => $stripeEvent->data->object->charge,
                                'processor' => 'stripe',
                                'amount' => $metadata->amount,
                                'currency' => $metadata->currency,
                                'interval' => $metadata->interval,
                                'status' => 'completed',
                                'coupon' => $metadata->coupon ?? null,
                                'tax_rates' => $metadata->tax_rates ?? null,
                                'customer' => $user->billing_information,
                            ]);

                            // Attempt to send the payment confirmation email
                            try {
                                Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                            }
                            catch (\Exception $e) {}
                        }
                    } else {
                        return response()->json([
                            'status' => 400
                        ], 400);
                    }
                }
            }
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Mollie webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws GuzzleException
     */
    public function mollie(Request $request)
    {
        $molliePaymentId = $request->input('id');

        if ($molliePaymentId) {
            $client = new HttpClient();

            $httpBaseUrl = 'https://api.mollie.com/v2/';

            try {
                $molliePaymentRequest = $client->post($httpBaseUrl . 'payments/' . $molliePaymentId, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . config('settings.mollie_key'),
                        'Content-Type' => 'application/json',
                    ]
                ]);

                $molliePayment = json_decode($molliePaymentRequest->getBody());
            } catch (\Exception $e) {
                // Invalid payload
                Log::info($e->getMessage());

                return response()->json([
                    'status' => 400
                ], 400);
            }

            $metadata = $molliePayment->metadata ?? null;

            // If a user was found;
            if (isset($metadata->user)) {
                $user = User::where('id', '=', $metadata->user)->first();

                // If a user was found
                if ($user) {
                    if ($molliePayment->status == 'paid') {
                        // If a coupon was used
                        if (isset($metadata->coupon) && $metadata->coupon) {
                            $coupon = Coupon::find($metadata->coupon);

                            // If a coupon was found
                            if ($coupon) {
                                // Increase the coupon usage
                                $coupon->increment('redeems', 1);
                            }
                        }

                        // If the payment is the first one
                        if ($molliePayment->sequenceType == 'first') {
                            // Attempt to create the subscription
                            try {
                                // If the user previously had a subscription, attempt to cancel it
                                if ($user->plan_subscription_id) {
                                    $user->planSubscriptionCancel();
                                }

                                $subscriptionStartDate = $metadata->interval == 'month' ? Carbon::now()->addMonth()->format('Y-m-d') : Carbon::now()->addYear()->format('Y-m-d');

                                $mollieSubscriptionRequest = $client->post($httpBaseUrl . 'customers/' . $molliePayment->customerId . '/subscriptions', [
                                    'headers' => [
                                        'Authorization' => 'Bearer ' . config('settings.mollie_key'),
                                        'Content-Type' => 'application/json',
                                    ],
                                    'json' => [
                                        'amount' => [
                                            'value' => $metadata->amount,
                                            'currency' => $metadata->currency
                                        ],
                                        'interval' => $metadata->interval == 'month' ? '1 month' : '12 months',
                                        'description' => $molliePayment->description . ' - ' . $molliePayment->id,
                                        'webhookUrl' => route('webhooks.mollie'),
                                        'metadata' => $metadata,
                                        'startDate' => $subscriptionStartDate
                                    ]
                                ]);

                                $mollieSubscription = json_decode($mollieSubscriptionRequest->getBody());

                                $user->plan_id = $metadata->plan;
                                $user->plan_amount = $metadata->amount;
                                $user->plan_currency = $metadata->currency;
                                $user->plan_interval = $metadata->interval;
                                $user->plan_payment_processor = 'mollie';
                                $user->plan_subscription_id = $mollieSubscription->id;
                                $user->plan_subscription_status = $mollieSubscription->status;
                                $user->plan_subscription_information = ['customerId' => $mollieSubscription->customerId];
                                $user->plan_created_at = Carbon::now();
                                $user->plan_recurring_at = $subscriptionStartDate;
                                $user->plan_ends_at = null;
                                $user->save();

                                // If the payment does not exist
                                if (!Payment::where([['processor', '=', 'mollie'], ['payment_id', '=', $molliePayment->id]])->exists()) {
                                    $payment = $this->paymentStore([
                                        'user_id' => $user->id,
                                        'plan_id' => $metadata->plan,
                                        'payment_id' => $molliePayment->id,
                                        'processor' => 'mollie',
                                        'amount' => $metadata->amount,
                                        'currency' => $metadata->currency,
                                        'interval' => $metadata->interval,
                                        'status' => 'completed',
                                        'coupon' => $metadata->coupon ?? null,
                                        'tax_rates' => $metadata->tax_rates ?? null,
                                        'customer' => $user->billing_information,
                                    ]);

                                    // Attempt to send the payment confirmation email
                                    try {
                                        Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                                    }
                                    catch (\Exception $e) {}
                                }
                            } catch (\Exception $e) {
                                // Invalid payload
                                Log::info($e->getMessage());

                                return response()->json([
                                    'status' => 400
                                ], 400);
                            }
                        }
                    }

                    if ($molliePayment->sequenceType == 'recurring') {
                        if ($user->plan_payment_processor == 'mollie' && $user->plan_subscription_id == $molliePayment->subscriptionId) {
                            // Attempt to retrieve the subscription
                            try {
                                $mollieSubscriptionRequest = $client->get($httpBaseUrl . 'customers/' . $molliePayment->customerId . '/subscriptions/ ' . $molliePayment->subscriptionId, [
                                    'headers' => [
                                        'Authorization' => 'Bearer ' . config('settings.mollie_key'),
                                        'Content-Type' => 'application/json',
                                    ],
                                    'json' => [
                                        'amount' => [
                                            'value' => $metadata->amount,
                                            'currency' => $metadata->currency
                                        ],
                                        'interval' => $metadata->interval == 'month' ? '1 month' : '12 months',
                                        'description' => $molliePayment->description . ' - ' . $molliePayment->id,
                                        'webhookUrl' => route('webhooks.mollie'),
                                        'metadata' => $metadata,
                                        'startDate' => $subscriptionStartDate
                                    ]
                                ]);

                                $mollieSubscription = json_decode($mollieSubscriptionRequest->getBody());

                                Log::info(print_r($mollieSubscription, true));

                                // Update the recurring date
                                if ($mollieSubscription->nextPaymentDate) {
                                    $user->plan_recurring_at = Carbon::createFromFormat('Y-m-d', $mollieSubscription->nextPaymentDate);
                                }

                                // Update the subscription status
                                if ($mollieSubscription->status) {
                                    $user->plan_subscription_status = $mollieSubscription->status;
                                }

                                // Update the recurring date
                                if ($mollieSubscription->nextPaymentDate) {
                                    $user->plan_recurring_at = Carbon::createFromFormat('Y-m-d', $mollieSubscription->nextPaymentDate);
                                }

                                // If the subscription was cancelled
                                if ($mollieSubscription->canceledAt) {
                                    $user->plan_ends_at = Carbon::createFromFormat('Y-m-d', $mollieSubscription->canceledAt);
                                } else if (!$mollieSubscription->nextPaymentDate) {
                                    $user->plan_ends_at = $user->plan_recurring_at;
                                }

                                // Reset the subscription recurring date
                                if (!empty($user->plan_ends_at)) {
                                    $user->plan_recurring_at = null;
                                }

                                $user->save();
                            } catch (\Exception $e) {

                            }
                        }
                    }
                }
            }
        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Paddle webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function paddle(Request $request) {
        if ($request->header('paddle-signature')) {
            $signatureSegments = explode(';', $request->header('paddle-signature'));

            if (isset($signatureSegments[0]) && isset($signatureSegments[1])) {
                $timeParameter = explode('=', $signatureSegments[0]);
                $signatureParameter = explode('=', $signatureSegments[1]);

                if (isset($timeParameter[1]) && isset($signatureParameter[1])) {
                    $signedPayload = $timeParameter[1] . ':' . $request->getContent();

                    $computedSignature = hash_hmac('sha256', $signedPayload, config('settings.paddle_wh_secret'));

                    // Validate the webhook signature
                    if (hash_equals($computedSignature, $signatureParameter[1])) {
                        $payload = json_decode($request->getContent());

                        $metadata = $payload->data->items[0]->price->custom_data ?? null;

                        if (isset($metadata->user)) {
                            if ($payload->event_type != 'subscription.created' && stripos($payload->event_type, 'subscription.') !== false) {
                                // Provide enough time for the subscription created event to be handled
                                sleep(3);
                            }

                            $user = User::where('id', '=', $metadata->user)->first();

                            if ($user) {
                                if ($payload->event_type == 'subscription.created') {
                                    // If the user previously had a subscription, attempt to cancel it
                                    if ($user->plan_subscription_id) {
                                        $user->planSubscriptionCancel();
                                    }

                                    $user->plan_id = $metadata->plan;
                                    $user->plan_amount = $metadata->amount;
                                    $user->plan_currency = $metadata->currency;
                                    $user->plan_interval = $metadata->interval;
                                    $user->plan_payment_processor = 'paddle';
                                    $user->plan_subscription_id = $payload->data->id;
                                    $user->plan_subscription_status = $payload->data->status;
                                    $user->plan_subscription_information = null;
                                    $user->plan_created_at = Carbon::now();
                                    $user->plan_recurring_at = $payload->data->next_billed_at ? Carbon::parse($payload->data->next_billed_at) : null;
                                    $user->plan_ends_at = null;
                                    $user->save();

                                    // If a coupon was used
                                    if (isset($metadata->coupon) && $metadata->coupon) {
                                        $coupon = Coupon::find($metadata->coupon);

                                        // If a coupon was found
                                        if ($coupon) {
                                            // Increase the coupon usage
                                            $coupon->increment('redeems', 1);
                                        }
                                    }
                                } elseif (stripos($payload->event_type, 'subscription.') !== false) {
                                    // If the subscription exists
                                    if ($user->plan_payment_processor == 'paddle' && $user->plan_subscription_id == $payload->data->id) {
                                        // Update the recurring date
                                        if ($payload->data->next_billed_at) {
                                            $user->plan_recurring_at = Carbon::parse($payload->data->next_billed_at);
                                        }

                                        // Update the subscription status
                                        if ($payload->data->status) {
                                            $user->plan_subscription_status = $payload->data->status;
                                        }

                                        // Update the subscription end date
                                        if (isset($payload->data->scheduled_change->action) && $payload->data->scheduled_change->action == 'cancel') {
                                            $user->plan_ends_at = Carbon::parse($payload->data->scheduled_change->effective_at);
                                        } elseif ($payload->data->canceled_at) {
                                            $user->plan_ends_at = Carbon::parse($payload->data->canceled_at);
                                        } else {
                                            $user->plan_ends_at = null;
                                        }

                                        // Reset the subscription recurring date
                                        if (!empty($user->plan_ends_at)) {
                                            $user->plan_recurring_at = null;
                                        }

                                        $user->save();
                                    }
                                } elseif ($payload->event_type == 'transaction.paid') {
                                    // Make sure the invoice contains the payment id
                                    if ($payload->data->id) {
                                        // If the payment does not exist
                                        if (!Payment::where([['processor', '=', 'paddle'], ['payment_id', '=', $payload->data->id]])->exists()) {
                                            $payment = $this->paymentStore([
                                                'user_id' => $user->id,
                                                'plan_id' => $metadata->plan,
                                                'payment_id' => $payload->data->id,
                                                'processor' => 'paddle',
                                                'amount' => $metadata->amount,
                                                'currency' => $metadata->currency,
                                                'interval' => $metadata->interval,
                                                'status' => 'completed',
                                                'coupon' => $metadata->coupon ?? null,
                                                'tax_rates' => $metadata->tax_rates ?? null,
                                                'customer' => $user->billing_information,
                                            ]);

                                            // Attempt to send the payment confirmation email
                                            try {
                                                Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                                            }
                                            catch (\Exception $e) {}
                                        }
                                    } else {
                                        return response()->json([
                                            'status' => 400
                                        ], 400);
                                    }
                                }
                            }
                        }
                    } else {
                        Log::info('Paddle signature validation failed.');

                        return response()->json([
                            'status' => 400
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'status' => 400
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 400
                ], 400);
            }
        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }
    }

    /**
     * Handle the Razorpay webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function razorpay(Request $request)
    {
        if ($request->header('x-razorpay-signature')) {
            $signature = $request->header('x-razorpay-signature');

            $computedSignature = hash_hmac('sha256', $request->getContent(), config('settings.razorpay_wh_secret'));

            // Validate the webhook signature
            if (hash_equals($computedSignature, $signature)) {
                $payload = json_decode($request->getContent());

                // Get the metadata
                $metadata = $payload->payload->subscription->entity->notes ?? null;

                if (isset($metadata->user)) {
                    $user = User::where('id', '=', $metadata->user)->first();

                    // If a user was found
                    if ($user) {
                        if ($payload->event == 'subscription.authenticated') {
                            // If the user previously had a subscription, attempt to cancel it
                            if ($user->plan_subscription_id) {
                                $user->planSubscriptionCancel();
                            }

                            $user->plan_id = $metadata->plan;
                            $user->plan_amount = $metadata->amount;
                            $user->plan_currency = $metadata->currency;
                            $user->plan_interval = $metadata->interval;
                            $user->plan_payment_processor = 'razorpay';
                            $user->plan_subscription_id = $payload->payload->subscription->entity->id;
                            $user->plan_subscription_status = $payload->payload->subscription->entity->status;
                            $user->plan_subscription_information = null;
                            $user->plan_created_at = Carbon::now();
                            $user->plan_recurring_at = $payload->payload->subscription->entity->charge_at ? Carbon::createFromTimestamp($payload->payload->subscription->entity->charge_at) : null;
                            $user->plan_ends_at = null;
                            $user->save();

                            // If a coupon was used
                            if (isset($metadata->coupon) && $metadata->coupon) {
                                $coupon = Coupon::find($metadata->coupon);

                                // If a coupon was found
                                if ($coupon) {
                                    // Increase the coupon usage
                                    $coupon->increment('redeems', 1);
                                }
                            }
                        } elseif (stripos($payload->event, 'subscription.') !== false) {
                            // If the subscription exists
                            if ($user->plan_payment_processor == 'razorpay' && $user->plan_subscription_id == $payload->payload->subscription->entity->id) {
                                // Update the recurring date
                                if ($payload->payload->subscription->entity->charge_at) {
                                    $user->plan_recurring_at = Carbon::createFromTimestamp($payload->payload->subscription->entity->charge_at);
                                }

                                // Update the subscription status
                                if ($payload->payload->subscription->entity->status) {
                                    $user->plan_subscription_status = $payload->payload->subscription->entity->status;
                                }

                                // Update the subscription end date
                                if ($payload->payload->subscription->entity->ended_at) {
                                    // Update the subscription end date and recurring date
                                    if (!empty($user->plan_recurring_at)) {
                                        $user->plan_ends_at = $user->plan_recurring_at;
                                        $user->plan_recurring_at = null;
                                    }
                                } else {
                                    $user->plan_ends_at = null;
                                }

                                $user->save();
                            }
                        }

                        if ($payload->event == 'subscription.charged') {
                            // If the payment does not exist
                            if (!Payment::where([['processor', '=', 'razorpay'], ['payment_id', '=', $payload->payload->payment->entity->id]])->exists()) {
                                $payment = $this->paymentStore([
                                    'user_id' => $user->id,
                                    'plan_id' => $metadata->plan,
                                    'payment_id' => $payload->payload->payment->entity->id,
                                    'processor' => 'razorpay',
                                    'amount' => $metadata->amount,
                                    'currency' => $metadata->currency,
                                    'interval' => $metadata->interval,
                                    'status' => 'completed',
                                    'coupon' => $metadata->coupon ?? null,
                                    'tax_rates' => $metadata->tax_rates ?? null,
                                    'customer' => $user->billing_information,
                                ]);

                                // Attempt to send the payment confirmation email
                                try {
                                    Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                                }
                                catch (\Exception $e) {}
                            }
                        }
                    }
                }
            } else {
                Log::info('Razorpay signature validation failed.');

                return response()->json([
                    'status' => 400
                ], 400);
            }
        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Paystack webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function paystack(Request $request)
    {
        if ($request->header('x-paystack-signature')) {
            $signature = $request->header('x-paystack-signature');

            $computedSignature = hash_hmac('sha512', $request->getContent(), config('settings.paystack_secret'));

            // Validate the webhook signature
            if (hash_equals($computedSignature, $signature)) {
                $payload = json_decode($request->getContent());

                // Parse the custom metadata parameters
                parse_str($payload->data->plan->description ?? null, $metadata);

                if (isset($metadata['user'])) {
                    $user = User::where('id', '=', $metadata['user'])->first();

                    // If a user was found
                    if ($user) {
                        if ($payload->event == 'subscription.create') {
                            // If the user previously had a subscription, attempt to cancel it
                            if ($user->plan_subscription_id) {
                                $user->planSubscriptionCancel();
                            }

                            $user->plan_id = $metadata['plan'];
                            $user->plan_amount = $metadata['amount'];
                            $user->plan_currency = $metadata['currency'];
                            $user->plan_interval = $metadata['interval'];
                            $user->plan_payment_processor = 'paystack';
                            $user->plan_subscription_id = $payload->data->subscription_code;
                            $user->plan_subscription_status = $payload->data->status;
                            $user->plan_subscription_information = null;
                            $user->plan_created_at = Carbon::now();
                            $user->plan_recurring_at = $payload->data->next_payment_date ? Carbon::createFromTimeString($payload->data->next_payment_date) : null;
                            $user->plan_ends_at = null;
                            $user->save();

                            // If a coupon was used
                            if (isset($metadata['coupon']) && $metadata['coupon']) {
                                $coupon = Coupon::find($metadata['coupon']);

                                // If a coupon was found
                                if ($coupon) {
                                    // Increase the coupon usage
                                    $coupon->increment('redeems', 1);
                                }
                            }
                        } elseif (stripos($payload->event, 'subscription.') !== false) {
                            // If the subscription exists
                            if ($user->plan_payment_processor == 'paystack' && $user->plan_subscription_id == $payload->data->subscription_code) {
                                // Update the recurring date
                                if ($payload->data->next_payment_date) {
                                    $user->plan_recurring_at = Carbon::createFromTimeString($payload->data->next_payment_date);
                                    $user->plan_ends_at = null;
                                } else {
                                    // Update the subscription end date and recurring date
                                    if (!empty($user->plan_recurring_at)) {
                                        $user->plan_ends_at = $user->plan_recurring_at;
                                        $user->plan_recurring_at = null;
                                    }
                                }

                                // Update the subscription status
                                if ($payload->data->status) {
                                    $user->plan_subscription_status = $payload->data->status;
                                }

                                $user->save();
                            }
                        }

                        if ($payload->event == 'charge.success') {
                            // If the payment does not exist
                            if (!Payment::where([['processor', '=', 'paystack'], ['payment_id', '=', $payload->data->reference]])->exists()) {
                                $payment = $this->paymentStore([
                                    'user_id' => $user->id,
                                    'plan_id' => $metadata['plan'],
                                    'payment_id' => $payload->data->reference,
                                    'processor' => 'paystack',
                                    'amount' => $metadata['amount'],
                                    'currency' => $metadata['currency'],
                                    'interval' => $metadata['interval'],
                                    'status' => 'completed',
                                    'coupon' => $metadata['coupon'] ?? null,
                                    'tax_rates' => $metadata['tax_rates'] ?? null,
                                    'customer' => $user->billing_information,
                                ]);

                                // Attempt to send the payment confirmation email
                                try {
                                    Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                                }
                                catch (\Exception $e) {}
                            }
                        }
                    }
                }
            } else {
                Log::info('Paystack signature validation failed.');

                return response()->json([
                    'status' => 400
                ], 400);
            }
        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Coinbase webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function coinbase(Request $request)
    {
        $computedSignature = hash_hmac('sha256', $request->getContent(), config('settings.coinbase_wh_secret'));

        // Validate the webhook signature
        if (hash_equals($computedSignature, $request->server('HTTP_X_CC_WEBHOOK_SIGNATURE'))) {
            $payload = json_decode($request->getContent());

            // If the payment was successful
            $metadata = $payload->event->data->metadata ?? null;

            if (isset($metadata->user)) {
                $user = User::where('id', '=', $metadata->user)->first();

                // If a user was found
                if ($user) {
                    if ($payload->event->type == 'charge:confirmed' || $payload->event->type == 'charge:resolved') {
                        // If the payment does not exist
                        if (!Payment::where([['processor', '=', 'coinbase'], ['payment_id', '=', $payload->event->data->code]])->exists()) {
                            $now = Carbon::now();

                            // If the user previously had a subscription, attempt to cancel it
                            if ($user->plan_subscription_id) {
                                $user->planSubscriptionCancel();
                            }

                            $user->plan_id = $metadata->plan;
                            $user->plan_amount = $metadata->amount;
                            $user->plan_currency = $metadata->currency;
                            $user->plan_interval = $metadata->interval;
                            $user->plan_payment_processor = 'coinbase';
                            $user->plan_subscription_id = $payload->event->data->code;
                            $user->plan_subscription_status = null;
                            $user->plan_subscription_information = null;
                            $user->plan_created_at = $now;
                            $user->plan_recurring_at = null;
                            $user->plan_ends_at = $metadata->interval == 'month' ? (clone $now)->addMonth() : (clone $now)->addYear();
                            $user->save();

                            // If a coupon was used
                            if (isset($metadata->coupon) && $metadata->coupon) {
                                $coupon = Coupon::find($metadata->coupon);

                                // If a coupon was found
                                if ($coupon) {
                                    // Increase the coupon usage
                                    $coupon->increment('redeems', 1);
                                }
                            }

                            $payment = $this->paymentStore([
                                'user_id' => $user->id,
                                'plan_id' => $metadata->plan,
                                'payment_id' => $payload->event->data->code,
                                'processor' => 'coinbase',
                                'amount' => $metadata->amount,
                                'currency' => $metadata->currency,
                                'interval' => $metadata->interval,
                                'status' => 'completed',
                                'coupon' => $metadata->coupon ?? null,
                                'tax_rates' => $metadata->tax_rates ?? null,
                                'customer' => $user->billing_information,
                            ]);

                            // Attempt to send the payment confirmation email
                            try {
                                Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                            }
                            catch (\Exception $e) {}
                        }
                    }
                }
            }
        } else {
            Log::info('Coinbase signature validation failed.');

            return response()->json([
                'status' => 400
            ], 400);
        }

        return response()->json([
            'status' => 200
        ], 200);
    }

    /**
     * Handle the Crypto.com webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cryptocom(Request $request)
    {
        if ($request->header('pay-signature')) {
            $signatureSegments = explode(',', $request->header('pay-signature'));

            if (isset($signatureSegments[0]) && isset($signatureSegments[1])) {
                $timeParameter = explode('=', $signatureSegments[0]);
                $signatureParameter = explode('=', $signatureSegments[1]);

                if (isset($timeParameter[1]) && isset($signatureParameter[1])) {
                    $signedPayload = $timeParameter[1] . '.' . $request->getContent();

                    $computedSignature = hash_hmac('sha256', $signedPayload, config('settings.cryptocom_wh_secret'));

                    // Validate the webhook signature
                    if (hash_equals($computedSignature, $signatureParameter[1])) {
                        $payload = json_decode($request->getContent());

                        // If the payment was successful
                        $metadata = $payload->data->object->metadata ?? null;

                        if (isset($metadata->user)) {
                            $user = User::where('id', '=', $metadata->user)->first();

                            // If a user was found
                            if ($user) {
                                if ($payload->data->object->status == 'succeeded') {
                                    // If the payment does not exist
                                    if (!Payment::where([['processor', '=', 'cryptocom'], ['payment_id', '=', $payload->data->object->id]])->exists()) {
                                        $now = Carbon::now();

                                        // If the user previously had a subscription, attempt to cancel it
                                        if ($user->plan_subscription_id) {
                                            $user->planSubscriptionCancel();
                                        }

                                        $user->plan_id = $metadata->plan;
                                        $user->plan_amount = $metadata->amount;
                                        $user->plan_currency = $metadata->currency;
                                        $user->plan_interval = $metadata->interval;
                                        $user->plan_payment_processor = 'coinbase';
                                        $user->plan_subscription_id = $payload->data->object->id;
                                        $user->plan_subscription_status = null;
                                        $user->plan_subscription_information = null;
                                        $user->plan_created_at = $now;
                                        $user->plan_recurring_at = null;
                                        $user->plan_ends_at = $metadata->interval == 'month' ? (clone $now)->addMonth() : (clone $now)->addYear();
                                        $user->save();

                                        // If a coupon was used
                                        if (isset($metadata->coupon) && $metadata->coupon) {
                                            $coupon = Coupon::find($metadata->coupon);

                                            // If a coupon was found
                                            if ($coupon) {
                                                // Increase the coupon usage
                                                $coupon->increment('redeems', 1);
                                            }
                                        }

                                        $payment = $this->paymentStore([
                                            'user_id' => $user->id,
                                            'plan_id' => $metadata->plan,
                                            'payment_id' => $payload->data->object->id,
                                            'processor' => 'cryptocom',
                                            'amount' => $metadata->amount,
                                            'currency' => $metadata->currency,
                                            'interval' => $metadata->interval,
                                            'status' => 'completed',
                                            'coupon' => $metadata->coupon ?? null,
                                            'tax_rates' => $metadata->tax_rates ?? null,
                                            'customer' => $user->billing_information,
                                        ]);

                                        // Attempt to send the payment confirmation email
                                        try {
                                            Mail::to($user->email)->locale($user->locale)->send(new PaymentMail($payment));
                                        }
                                        catch (\Exception $e) {}
                                    }
                                }
                            }
                        }
                    } else {
                        Log::info('Crypto.com signature validation failed.');

                        return response()->json([
                            'status' => 400
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'status' => 400
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 400
                ], 400);
            }
        } else {
            return response()->json([
                'status' => 400
            ], 400);
        }

        return response()->json([
            'status' => 200
        ], 200);
    }
}