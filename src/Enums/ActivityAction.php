<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * What an entry of the activity log records. The activity log's action filter also takes "sensitive", for the actions that matter most to security, such as those on payout addresses, sign-ins, API keys and members.
 */
enum ActivityAction: string
{
    case WalletsChanged = 'wallets.changed';
    case PayoutAddressAccessChanged = 'wallets.api_access_changed';
    case SecondFactorRequirementChanged = 'team.second_factor_requirement_changed';
    case PaymentSettingsUpdated = 'payment_settings.updated';
    case ProfileUpdated = 'profile.updated';
    case BusinessDetailsUpdated = 'business_details.updated';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyUpdated = 'api_key.updated';
    case ApiKeyRevoked = 'api_key.revoked';
    case WebhookCreated = 'webhook.created';
    case WebhookUpdated = 'webhook.updated';
    case WebhookDeleted = 'webhook.deleted';
    case WebhookSecretRotated = 'webhook.secret_rotated';
    case MemberInvited = 'member.invited';
    case MemberJoined = 'member.joined';
    case MemberRoleChanged = 'member.role_changed';
    case MemberRemoved = 'member.removed';
    case PaymentLinkCreated = 'payment_link.created';
    case PaymentLinkUpdated = 'payment_link.updated';
    case PaymentLinkArchived = 'payment_link.archived';
    case PaymentLinkRestored = 'payment_link.restored';
    case InvitationCanceled = 'member.invitation_canceled';
    case ChargePaymentRequested = 'charge.payment_requested';
    case WebhookDisabled = 'webhook.disabled';
    case ChargeCanceled = 'charge.canceled';
    case ChargeAccepted = 'charge.accepted';
    case RefundRecorded = 'refund.recorded';
    case InvoiceVoided = 'invoice.voided';
    case PlanPaymentStarted = 'plan.payment_started';
    case SubscriptionCanceled = 'plan.subscription_canceled';
    case SubscriptionResumed = 'plan.subscription_resumed';
    case SignedIn = 'auth.signed_in';
    case SignInFailed = 'auth.sign_in_failed';
    case PasswordChanged = 'auth.password_changed';
    case TwoFactorEnabled = 'auth.two_factor_enabled';
    case TwoFactorDisabled = 'auth.two_factor_disabled';
    case SignedOutElsewhere = 'auth.signed_out_elsewhere';
    case TwoFactorFailed = 'auth.two_factor_failed';
    case EmailChanged = 'auth.email_changed';
    case PasskeyAdded = 'auth.passkey_added';
    case PasskeyRemoved = 'auth.passkey_removed';
    case CouponCreated = 'coupon.created';
    case CouponUpdated = 'coupon.updated';
    case CouponDeleted = 'coupon.deleted';
    case AppConnected = 'auth.app_connected';
    case AppDisconnected = 'auth.app_disconnected';
    case ChatConnected = 'chat.connected';
    case ChatUpdated = 'chat.updated';
    case ChatRemoved = 'chat.removed';
    case ChatDisabled = 'chat.disabled';
    case SanctionsMatched = 'sanctions.matched';
    case SanctionsAccepted = 'sanctions.accepted';
    case SanctionsReturnRequested = 'sanctions.return_requested';
    case SocialAccountConnected = 'auth.social_connected';
    case SocialAccountDisconnected = 'auth.social_disconnected';
}
