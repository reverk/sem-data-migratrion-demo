import pandas as pd
import numpy as np
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

# Data source: https://www.kaggle.com/datasets/ahmedmohamed2003/cafe-sales-dirty-data-for-cleaning-training?resource=download

# Menu prices reference
MENU_PRICES = {
    'Coffee': 2.0,
    'Tea': 1.5,
    'Sandwich': 4.0,
    'Salad': 5.0,
    'Cake': 3.0,
    'Cookie': 1.0,
    'Smoothie': 4.0,
    'Juice': 3.0
}

def load_data():
    """Load the dirty cafe sales data"""
    print("Loading data...")
    df = pd.read_csv('dirty_cafe_sales.csv')
    print(f"Dataset loaded: {df.shape[0]} rows, {df.shape[1]} columns")
    return df

def perform_eda(df):
    """Perform Exploratory Data Analysis"""
    print("\n" + "="*50)
    print("EXPLORATORY DATA ANALYSIS")
    print("="*50)
    
    print(f"\nDataset shape: {df.shape}")
    print(f"Columns: {list(df.columns)}")
    
    print("\nData types:")
    print(df.dtypes)
    
    print("\nFirst 5 rows:")
    print(df.head())
    
    print("\nMissing/Invalid values (null/empty/ERROR/UNKNOWN):")
    missing_counts = df.isnull().sum()
    empty_counts = (df == '').sum()
    error_counts = (df == 'ERROR').sum()
    unknown_counts = (df == 'UNKNOWN').sum()
    total_problematic = missing_counts + empty_counts + error_counts + unknown_counts
    
    missing_df = pd.DataFrame({
        'Column': df.columns,
        'Null_Values': missing_counts,
        'Empty_Values': empty_counts,
        'ERROR_Values': error_counts,
        'UNKNOWN_Values': unknown_counts,
        'Total_Problematic': total_problematic,
        'Problematic_Percentage': (total_problematic / len(df)) * 100
    })
    print(missing_df)
    
    print("\nUnique values per column:")
    for col in df.columns:
        unique_values = df[col].nunique()
        print(f"{col}: {unique_values} unique values")
        
        # Show problematic values
        unique_vals = df[col].unique()
        problematic = [val for val in unique_vals if val in ['ERROR', 'UNKNOWN', '', np.nan]]
        if problematic:
            print(f"  - Problematic values found: {problematic}")
    
    # Check for calculation inconsistencies
    print("\nChecking calculation consistency (Quantity * Price Per Unit = Total Spent):")
    df_calc = df.copy()
    
    # Convert numeric columns, handling errors
    for col in ['Quantity', 'Price Per Unit', 'Total Spent']:
        df_calc[col] = pd.to_numeric(df_calc[col], errors='coerce')
    
    # Calculate expected total
    df_calc['Expected_Total'] = df_calc['Quantity'] * df_calc['Price Per Unit']
    df_calc['Calc_Error'] = ~np.isclose(df_calc['Total Spent'], df_calc['Expected_Total'], rtol=1e-09, equal_nan=True)
    
    calc_errors = df_calc['Calc_Error'].sum()
    print(f"Rows with calculation errors: {calc_errors}")
    
    return missing_df, calc_errors

def clean_data(df):
    """Main data cleaning function"""
    print("\n" + "="*50)
    print("DATA CLEANING")
    print("="*50)
    
    df_clean = df.copy()
    print(f"Starting with {len(df_clean)} rows")
    
    # Initialize cleaning report dictionary
    cleaning_report = {
        'initial_rows': len(df_clean),
        'operations_performed': [],
        'rows_removed_by_operation': {},
        'values_imputed': {},
        'imputation_values_used': {},
        'price_corrections': 0,
        'total_calculations_fixed': 0
    }
    
    # 1. Handle missing values and replace ERROR/UNKNOWN with NaN
    print("\n1. Handling missing and invalid values...")
    
    # Count problematic values before cleaning
    error_counts = (df == 'ERROR').sum().sum()
    unknown_counts = (df == 'UNKNOWN').sum().sum()
    empty_counts = (df == '').sum().sum()
    
    # Replace empty strings and problematic values with NaN
    df_clean = df_clean.replace(['', 'ERROR', 'UNKNOWN'], np.nan)
    
    cleaning_report['operations_performed'].append('Replaced ERROR/UNKNOWN/empty values with NaN')
    cleaning_report['values_imputed']['ERROR_values_replaced'] = error_counts
    cleaning_report['values_imputed']['UNKNOWN_values_replaced'] = unknown_counts
    cleaning_report['values_imputed']['empty_values_replaced'] = empty_counts
    
    print("Missing values after cleaning:")
    missing_after = df_clean.isnull().sum()
    for col, count in missing_after.items():
        if count > 0:
            print(f"  {col}: {count} missing values ({count/len(df_clean)*100:.1f}%)")
    
    # 2. Clean Transaction ID - should never be missing
    print("\n2. Cleaning Transaction ID...")
    rows_before = len(df_clean)
    df_clean = df_clean.dropna(subset=['Transaction ID'])
    rows_after = len(df_clean)
    rows_removed = rows_before - rows_after
    print(f"Removed {rows_removed} rows with missing Transaction ID")
    
    cleaning_report['operations_performed'].append('Removed rows with missing Transaction ID')
    cleaning_report['rows_removed_by_operation']['missing_transaction_id'] = rows_removed
    
    # 3. Clean Item column
    print("\n3. Cleaning Item column...")
    missing_items = df_clean['Item'].isnull().sum()
    print(f"Rows with missing items: {missing_items}")
    
    # For missing items, try to infer from price
    # Meaning: If there's menu item, then the price should be in the menu prices
    mask_missing_item = df_clean['Item'].isnull()
    items_inferred = 0
    if mask_missing_item.sum() > 0:
        print("Attempting to infer missing items from price...")
        for idx in df_clean[mask_missing_item].index:
            price = pd.to_numeric(df_clean.loc[idx, 'Price Per Unit'], errors='coerce')
            if not pd.isna(price):
                # Find matching item from menu
                for item, menu_price in MENU_PRICES.items():
                    if price == menu_price:
                        df_clean.loc[idx, 'Item'] = item
                        print(f"  Inferred item '{item}' for row {idx} based on price ${price}")
                        items_inferred += 1
                        break
                    
    # Remove rows where item is still missing
    rows_before = len(df_clean)
    df_clean = df_clean.dropna(subset=['Item'])
    rows_after = len(df_clean)
    items_removed = rows_before - rows_after
    if rows_before != rows_after:
        print(f"Removed {items_removed} rows with unresolvable missing items")
    
    cleaning_report['operations_performed'].append('Inferred missing items from prices and removed unresolvable items')
    cleaning_report['values_imputed']['items_inferred_from_price'] = items_inferred
    cleaning_report['rows_removed_by_operation']['unresolvable_missing_items'] = items_removed
    
    # 4. Clean numeric columns
    print("\n4. Cleaning numeric columns...")
    
    # Clean Quantity
    df_clean['Quantity'] = pd.to_numeric(df_clean['Quantity'], errors='coerce')
    invalid_qty = df_clean['Quantity'].isnull() | (df_clean['Quantity'] <= 0)
    invalid_qty_count = invalid_qty.sum()
    if invalid_qty_count > 0:
        print(f"Found {invalid_qty_count} invalid quantities")
        # Set invalid quantities to 1 (most common case)
        imputation_value = 1
        df_clean.loc[invalid_qty, 'Quantity'] = imputation_value
        print(f"Set invalid quantities to {imputation_value}")
        
        cleaning_report['operations_performed'].append('Fixed invalid quantities by setting to 1')
        cleaning_report['values_imputed']['invalid_quantities_fixed'] = invalid_qty_count
        cleaning_report['imputation_values_used']['invalid_quantities'] = imputation_value
    
    # Clean Price Per Unit using menu prices
    print("Cleaning Price Per Unit...")
    df_clean['Price Per Unit'] = pd.to_numeric(df_clean['Price Per Unit'], errors='coerce')
    
    # Fill missing prices from menu
    missing_price_mask = df_clean['Price Per Unit'].isnull()
    prices_filled = 0
    price_imputation_details = {}
    for idx in df_clean[missing_price_mask].index:
        item = df_clean.loc[idx, 'Item']
        if item in MENU_PRICES:
            menu_price = MENU_PRICES[item]
            df_clean.loc[idx, 'Price Per Unit'] = menu_price
            print(f"  Fixed price for {item} at row {idx}")
            prices_filled += 1
            # Track imputation values used
            if item not in price_imputation_details:
                price_imputation_details[item] = menu_price
    
    # Validate prices against menu
    prices_corrected = 0
    price_correction_details = {}
    for idx, row in df_clean.iterrows():
        item = row['Item']
        price = row['Price Per Unit']
        if item in MENU_PRICES and not pd.isna(price):
            if price != MENU_PRICES[item]:
                print(f"  Correcting price for {item} from ${price} to ${MENU_PRICES[item]} at row {idx}")
                df_clean.loc[idx, 'Price Per Unit'] = MENU_PRICES[item]
                prices_corrected += 1
                # Track correction values used
                if item not in price_correction_details:
                    price_correction_details[item] = MENU_PRICES[item]
    
    cleaning_report['operations_performed'].append('Filled missing prices and corrected invalid prices using menu')
    cleaning_report['values_imputed']['missing_prices_filled'] = prices_filled
    cleaning_report['price_corrections'] = prices_corrected
    cleaning_report['imputation_values_used']['missing_prices'] = price_imputation_details
    cleaning_report['imputation_values_used']['corrected_prices'] = price_correction_details
    
    # 5. Recalculate Total Spent for all rows
    print("\n5. Recalculating Total Spent for all rows...")
    
    # Since majority of prices and quantities have been corrected, recalculate all totals
    df_clean['Total Spent'] = df_clean['Quantity'] * df_clean['Price Per Unit']
    print(f"Recalculated Total Spent for all {len(df_clean)} rows")
    
    cleaning_report['operations_performed'].append('Recalculated Total Spent for all rows')
    cleaning_report['total_calculations_fixed'] = len(df_clean)
    
    # 6. Clean Payment Method
    print("\n6. Cleaning Payment Method...")
    missing_payment = df_clean['Payment Method'].isnull().sum()
    if missing_payment > 0:
        print(f"Found {missing_payment} missing payment methods")
        # Fill with most common payment method
        most_common_payment = df_clean['Payment Method'].mode()[0] if len(df_clean['Payment Method'].mode()) > 0 else 'Cash'
        df_clean['Payment Method'] = df_clean['Payment Method'].fillna(most_common_payment)
        print(f"Filled missing payment methods with '{most_common_payment}'")
        
        cleaning_report['operations_performed'].append(f'Filled missing payment methods with mode ({most_common_payment})')
        cleaning_report['values_imputed']['payment_methods_filled'] = missing_payment
        cleaning_report['imputation_values_used']['payment_method'] = most_common_payment
    
    # 7. Clean Location
    print("\n7. Cleaning Location...")
    missing_location = df_clean['Location'].isnull().sum()
    if missing_location > 0:
        print(f"Found {missing_location} missing locations")
        # Fill with most common location
        most_common_location = df_clean['Location'].mode()[0] if len(df_clean['Location'].mode()) > 0 else 'In-store'
        df_clean['Location'] = df_clean['Location'].fillna(most_common_location)
        print(f"Filled missing locations with '{most_common_location}'")
        
        cleaning_report['operations_performed'].append(f'Filled missing locations with mode ({most_common_location})')
        cleaning_report['values_imputed']['locations_filled'] = missing_location
        cleaning_report['imputation_values_used']['location'] = most_common_location
    
    # 8. Clean Transaction Date
    print("\n8. Cleaning Transaction Date...")
    missing_dates = df_clean['Transaction Date'].isnull().sum()
    if missing_dates > 0:
        print(f"Found {missing_dates} missing dates")
        # Remove rows with missing dates as they're critical
        rows_before = len(df_clean)
        df_clean = df_clean.dropna(subset=['Transaction Date'])
        rows_after = len(df_clean)
        dates_removed = rows_before - rows_after
        print(f"Removed {dates_removed} rows with missing dates")
        
        cleaning_report['operations_performed'].append('Removed rows with missing transaction dates')
        cleaning_report['rows_removed_by_operation']['missing_transaction_dates'] = dates_removed
    
    # Validate date format
    try:
        df_clean['Transaction Date'] = pd.to_datetime(df_clean['Transaction Date'])
        print("Successfully converted dates to datetime format")
        cleaning_report['operations_performed'].append('Successfully converted dates to datetime format')
    except:
        print("Some dates may have formatting issues - will keep as text for manual review")
        cleaning_report['operations_performed'].append('Date conversion failed - kept as text for manual review')
    
    # Finalize cleaning report
    cleaning_report['final_rows'] = len(df_clean)
    cleaning_report['total_rows_removed'] = cleaning_report['initial_rows'] - len(df_clean)
    cleaning_report['data_retention_rate'] = (len(df_clean) / cleaning_report['initial_rows']) * 100
    
    print(f"\nCleaning complete! Final dataset: {len(df_clean)} rows")
    return df_clean, cleaning_report

def generate_cleaning_report(cleaning_report):
    """Generate a detailed data cleaning report"""
    print("\n" + "="*60)
    print("DETAILED DATA CLEANING REPORT")
    print("="*60)
    
    # Overview
    print(f"\n[OVERVIEW] CLEANING SUMMARY")
    print("-" * 30)
    print(f"Initial rows: {cleaning_report['initial_rows']:,}")
    print(f"Final rows: {cleaning_report['final_rows']:,}")
    print(f"Total rows removed: {cleaning_report['total_rows_removed']:,}")
    print(f"Data retention rate: {cleaning_report['data_retention_rate']:.2f}%")
    
    # Operations performed
    print(f"\n[OPERATIONS] CLEANING STEPS PERFORMED")
    print("-" * 40)
    for i, operation in enumerate(cleaning_report['operations_performed'], 1):
        print(f"{i:2d}. {operation}")
    
    # Rows removed by operation
    if cleaning_report['rows_removed_by_operation']:
        print(f"\n[REMOVED] ROWS REMOVED BY OPERATION")
        print("-" * 35)
        total_removed = 0
        for operation, count in cleaning_report['rows_removed_by_operation'].items():
            if count > 0:
                print(f"• {operation.replace('_', ' ').title()}: {count:,} rows")
                total_removed += count
        print(f"Total removed: {total_removed:,} rows")
    
    # Values imputed/fixed
    if cleaning_report['values_imputed']:
        print(f"\n[FIXED] VALUES IMPUTED/FIXED")
        print("-" * 25)
        for operation, count in cleaning_report['values_imputed'].items():
            if count > 0:
                print(f"• {operation.replace('_', ' ').title()}: {count:,} values")
    
    # Price corrections
    if cleaning_report['price_corrections'] > 0:
        print(f"\n[PRICES] PRICE CORRECTIONS")
        print("-" * 20)
        print(f"• Incorrect prices corrected: {cleaning_report['price_corrections']:,}")
    
    # Total calculations
    if cleaning_report['total_calculations_fixed'] > 0:
        print(f"\n[CALCULATIONS] TOTAL SPENT UPDATES")
        print("-" * 22)
        print(f"• Total Spent recalculated for: {cleaning_report['total_calculations_fixed']:,} rows")
    
    # Imputation values used
    if cleaning_report['imputation_values_used']:
        print(f"\n[IMPUTATION] VALUES USED FOR IMPUTATION")
        print("-" * 35)
        for field, value in cleaning_report['imputation_values_used'].items():
            if isinstance(value, dict):
                print(f"• {field.replace('_', ' ').title()}:")
                for item, item_value in value.items():
                    print(f"  - {item}: ${item_value}")
            else:
                print(f"• {field.replace('_', ' ').title()}: {value}")
    
    print(f"\n[SUCCESS] CLEANING COMPLETED")
    print("-" * 18)
    print("* All data cleaning operations completed successfully!")
    print("* Dataset is now ready for analysis and migration")

def generate_summary_report(df_original, df_clean):
    """Generate a summary report of the cleaning process"""
    print("\n" + "="*50)
    print("CLEANING SUMMARY REPORT")
    print("="*50)
    
    print(f"Original rows: {len(df_original)}")
    print(f"Cleaned rows: {len(df_clean)}")
    print(f"Rows removed: {len(df_original) - len(df_clean)}")
    print(f"Data retention rate: {len(df_clean)/len(df_original)*100:.2f}%")
    
    print(f"\nFinal data quality:")
    print("Missing values:")
    final_missing = df_clean.isnull().sum()
    for col, count in final_missing.items():
        print(f"  {col}: {count} missing values ({count/len(df_clean)*100:.1f}%)")
    
    if final_missing.sum() == 0:
        print("[SUCCESS] No missing values in final dataset!")
    
    # Show unique values for categorical columns
    print("\nFinal unique values:")
    categorical_cols = ['Item', 'Payment Method', 'Location']
    for col in categorical_cols:
        if col in df_clean.columns:
            unique_vals = sorted(df_clean[col].unique())
            print(f"  {col}: {unique_vals}")

def main():
    """Main execution function"""
    # Load data
    df = load_data()
    
    # Perform EDA
    missing_report, calc_errors = perform_eda(df)
    
    # Clean data
    df_clean, cleaning_report = clean_data(df)
    
    # Generate detailed cleaning report
    generate_cleaning_report(cleaning_report)
    
    # Generate summary
    generate_summary_report(df, df_clean)
    
    # Save cleaned data
    print("\n" + "="*50)
    print("SAVING CLEANED DATA")
    print("="*50)
    
    # Save main cleaned dataset
    clean_filename = 'cleaned_cafe_sales.csv'
    df_clean.to_csv(clean_filename, index=False)
    print(f"[SAVED] Cleaned data saved to: {clean_filename}")
    
    # Save EDA report
    eda_filename = 'eda_report_corrected.csv'
    missing_report.to_csv(eda_filename, index=False)
    print(f"[SAVED] Corrected EDA report saved to: {eda_filename}")
    
    # Save cleaning report as JSON
    import json
    
    # Convert numpy types to native Python types for JSON serialization
    def convert_numpy_types(obj):
        """Convert numpy types to native Python types for JSON serialization"""
        if isinstance(obj, dict):
            return {key: convert_numpy_types(value) for key, value in obj.items()}
        elif isinstance(obj, list):
            return [convert_numpy_types(item) for item in obj]
        elif isinstance(obj, np.integer):
            return int(obj)
        elif isinstance(obj, np.floating):
            return float(obj)
        elif isinstance(obj, np.ndarray):
            return obj.tolist()
        else:
            return obj
    
    cleaned_report = convert_numpy_types(cleaning_report)
    cleaning_report_filename = 'data_cleaning_report.json'
    with open(cleaning_report_filename, 'w') as f:
        json.dump(cleaned_report, f, indent=2)
    print(f"[SAVED] Detailed cleaning report saved to: {cleaning_report_filename}")
    
    # Show sample of cleaned data
    print(f"\nSample of cleaned data (first 5 rows):")
    print(df_clean.head())
    
    print(f"\n[COMPLETE] Data cleaning completed successfully!")
    print(f"Final dataset contains {len(df_clean)} clean records")

if __name__ == "__main__":
    main()