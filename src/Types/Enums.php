<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Types;

/**
 * Claude model identifiers
 */
enum Model: string
{
    case CLAUDE_SONNET = 'claude-sonnet-4-5-20250929';
    case CLAUDE_OPUS = 'claude-opus-4-5-20251101';
}

/**
 * Permission modes for tool execution
 */
enum PermissionMode: string
{
    case DEFAULT = 'default';
    case PLAN = 'plan';
    case BYPASS_PERMISSIONS = 'bypassPermissions';
}

/**
 * Budget time windows
 */
enum BudgetWindow: string
{
    case HOUR = 'hour';
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
}

/**
 * Message types
 */
enum MessageType: string
{
    case INIT = 'init';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
    case RESULT = 'result';
    case STREAM_EVENT = 'stream_event';
    case CONTROL = 'control';
    case ERROR = 'error';
    case PING = 'ping';
    case PONG = 'pong';
    case TOOL_CALL = 'tool_call';
    case TOOL_RESULT = 'tool_result';
}

/**
 * Result subtypes
 */
enum ResultSubtype: string
{
    case SUCCESS = 'success';
    case ERROR_MAX_TURNS = 'error_max_turns';
    case ERROR_DURING_EXECUTION = 'error_during_execution';
    case ERROR_BUDGET = 'error_budget';
    case ERROR_CONCURRENCY = 'error_concurrency';
    case ERROR_AUTHENTICATION = 'error_authentication';
}

/**
 * System message subtypes
 */
enum SystemSubtype: string
{
    case INIT = 'init';
    case COMPACT_BOUNDARY = 'compact_boundary';
}

/**
 * Control actions
 */
enum ControlAction: string
{
    case READY = 'ready';
    case SESSION_INFO = 'session_info';
    case END_INPUT = 'end_input';
    case CLOSE = 'close';
}

/**
 * Session states
 */
enum SessionState: string
{
    case IDLE = 'idle';
    case INITIALIZING = 'initializing';
    case READY = 'ready';
    case PROCESSING = 'processing';
    case WAITING_TOOL = 'waiting_tool';
    case COMPLETED = 'completed';
    case ERROR = 'error';
}

/**
 * Tool execution location
 */
enum ExecuteLocation: string
{
    case SERVER = 'server';
    case CLIENT = 'client';
    case BROWSER = 'browser';
}
