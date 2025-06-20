#!/bin/bash

# --- Configuration ---
TARGET_URL="https://vms-api-main-branch-zuipth.laravel.cloud/api/mumineen/family-by-its-id?its_id=No6tvIxy5BeCyTe0O8xpvwXkndo04%2FwZr1%2BtmSAoffY%3D&event_id=1"
TOKEN_HEADER="No6tvIxy5BeCyTe0O8xpvwXkndo04%2FwZr1%2BtmSAoffY%3D" # The value for the 'Token' header
NUM_REQUESTS=500              # Total number of requests to send (changed back to 500 as per previous request)
TARGET_RPS=50                 # Target requests per second

# Calculate the delay needed per request to achieve the TARGET_RPS
# e.g., for 50 RPS, delay = 1 second / 50 requests = 0.02 seconds per request
DELAY_SECONDS=$(awk "BEGIN {print 1 / $TARGET_RPS}")

# --- Initialize Variables ---
SUCCESS_COUNT=0
FAILURE_COUNT=0
REQUEST_COUNT=0

# --- Function to display progress ---
function show_progress {
    local current=$1
    local total=$2
    local percent=$(( (current * 100) / total ))
    local bar_length=50
    local filled_length=$(( (percent * bar_length) / 100 ))
    local empty_length=$(( bar_length - filled_length ))
    local filled_bar=$(printf "%-${filled_length}s" | sed 's/ /#/g')
    local empty_bar=$(printf "%-${empty_length}s" | sed 's/ /-/g')

    printf "\rProgress: [%s%s] %d%% (%d/%d) Success: %d, Fail: %d" \
           "$filled_bar" "$empty_bar" "$percent" "$current" "$total" \
           "$SUCCESS_COUNT" "$FAILURE_COUNT"
}

echo "--- Starting Load Test ---"
echo "Target URL: $TARGET_URL"
echo "Number of Requests: $NUM_REQUESTS"
echo "Target Rate: ${TARGET_RPS} requests per second"
echo "Calculated Delay between requests: ${DELAY_SECONDS} seconds"
echo "--- Headers ---"
echo "Token: $TOKEN_HEADER"
echo "--------------------------"

# Record the start time (seconds and nanoseconds)
START_TIME=$(date +%s.%N)

# --- Main Load Test Loop ---
for (( i=1; i<=NUM_REQUESTS; i++ ))
do
    REQUEST_COUNT=$i
    # Use curl to make the request.
    # -s: Silent mode (don't show progress meter or error messages)
    # -o /dev/null: Discard output (don't save the response body)
    # -w "%{http_code}": Print only the HTTP status code
    # -H "Token: <YOUR_TOKEN_VALUE>": Add the custom 'Token' header
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Token: $TOKEN_HEADER" "$TARGET_URL")

    if [[ "$HTTP_STATUS" -ge 200 && "$HTTP_STATUS" -lt 400 ]]; then
        # HTTP status codes 2xx and 3xx are generally considered successful
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        FAILURE_COUNT=$((FAILURE_COUNT + 1))
    fi

    show_progress "$REQUEST_COUNT" "$NUM_REQUESTS"

    # Add the calculated delay between requests
    sleep "$DELAY_SECONDS"
done

# Record the end time (seconds and nanoseconds)
END_TIME=$(date +%s.%N)

echo "" # New line after progress bar
echo "--- Load Test Complete ---"
echo "Total Requests: $NUM_REQUESTS"
echo "Successful Requests: $SUCCESS_COUNT"
echo "Failed Requests: $FAILURE_COUNT"

# Calculate elapsed time using 'bc' for floating-point arithmetic
ELAPSED_TIME=$(echo "$END_TIME - $START_TIME" | bc -l)
echo "Total Elapsed Time: ${ELAPSED_TIME} seconds"

# Calculate Actual Requests Per Second
ACTUAL_RPS=$(echo "scale=2; $NUM_REQUESTS / $ELAPSED_TIME" | bc -l)
echo "Actual Requests Per Second: ${ACTUAL_RPS}"

echo "--------------------------"